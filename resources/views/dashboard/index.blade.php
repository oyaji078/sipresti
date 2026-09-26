@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Ringkasan data pemantauan gizi balita — seluruh angka dihitung langsung dari basis data.')

@section('content')

@unless ($mlConfigured)
    <div class="alert alert-warning sip-no-print">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Layanan prediksi (ML API) belum dikonfigurasi. Fitur analisis risiko belum dapat dijalankan.
    </div>
@endunless

<div class="row g-3 mb-4">
    @php
        $cards = [
            ['Total Balita', $counters['toddlers'], 'bi-people', 'sip-soft-primary'],
            ['Total Pemeriksaan', $counters['measurements'], 'bi-clipboard2-pulse', 'sip-soft-info'],
            ['Total Prediksi', $counters['predictions'], 'bi-cpu', 'sip-soft-primary'],
            ['Berisiko', $counters['at_risk'], 'bi-exclamation-triangle', 'sip-soft-danger'],
            ['Tidak Berisiko', $counters['not_at_risk'], 'bi-shield-check', 'sip-soft-success'],
        ];
    @endphp

    @foreach ($cards as [$label, $value, $icon, $tone])
        <div class="col-6 col-lg">
            <div class="sip-stat d-flex align-items-center gap-3">
                <div class="sip-stat__icon {{ $tone }}"><i class="bi {{ $icon }}"></i></div>
                <div>
                    <div class="sip-stat__value">{{ number_format($value, 0, ',', '.') }}</div>
                    <div class="sip-stat__label">{{ $label }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart me-1"></i>Distribusi Hasil Prediksi</div>
            <div class="card-body">
                @if (count($distribution) > 0)
                    <canvas id="chartDistribution" height="220"></canvas>
                    <div class="mt-3 small">
                        @foreach ($distribution as $label => $total)
                            <div class="d-flex justify-content-between border-bottom py-1">
                                <span>{{ $label }}</span>
                                <span class="fw-semibold">{{ number_format($total, 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="sip-empty">
                        <i class="bi bi-pie-chart"></i>
                        Belum ada hasil prediksi.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-graph-up me-1"></i>Tren Pemeriksaan &amp; Prediksi (12 Bulan)</div>
            <div class="card-body">
                <canvas id="chartTrend" height="140"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-1"></i>Prediksi Terbaru</span>
                <a href="{{ route('prediksi.index') }}" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Balita</th>
                            <th>Hasil</th>
                            <th class="text-end">Probabilitas</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($latestPredictions as $p)
                            <tr>
                                <td>
                                    <a href="{{ route('balita.show', $p->toddler_id) }}" class="text-decoration-none fw-semibold">{{ $p->toddler->name }}</a>
                                    <div class="text-muted small">{{ $p->toddler->code }}</div>
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $p->statusClass() }}">
                                        <i class="bi {{ $p->statusIcon() }} me-1"></i>{{ $p->statusLabel() }}
                                    </span>
                                </td>
                                <td class="text-end fw-semibold">{{ $p->probabilityPercent() }}</td>
                                <td class="small text-muted">{{ $p->predicted_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="sip-empty">
                                        <i class="bi bi-cpu"></i>
                                        Belum ada prediksi yang dijalankan.
                                        @if (auth()->user()->isPetugasGizi())
                                            <div class="mt-2">
                                                <a href="{{ route('prediksi.create') }}" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-play-circle me-1"></i>Jalankan Analisis
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-exclamation-triangle me-1"></i>Balita dengan Hasil Berisiko</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Balita</th>
                            <th>Posyandu</th>
                            <th class="text-end">Prob.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($atRisk as $p)
                            <tr>
                                <td>
                                    <a href="{{ route('balita.show', $p->toddler_id) }}" class="text-decoration-none fw-semibold">{{ $p->toddler->name }}</a>
                                    <div class="text-muted small">{{ $p->toddler->code }} &middot; {{ $p->toddler->genderLabel() }}</div>
                                </td>
                                <td class="small">{{ $p->toddler->posyandu ?? '-' }}</td>
                                <td class="text-end fw-semibold text-danger">{{ $p->probabilityPercent() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">
                                    <div class="sip-empty">
                                        <i class="bi bi-shield-check"></i>
                                        Belum ada balita dengan hasil berisiko.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<p class="sip-disclaimer mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>{{ config('sipresti.disclaimer') }}
</p>
@endsection

@push('scripts')
<script>
(function () {
    var distribution = @json($distribution);
    var measurementTrend = @json($measurementTrend);
    var predictionTrend = @json($predictionTrend);

    var palette = ['#dc2626', '#16a34a', '#0284c7', '#d97706', '#7c3aed', '#0f766e'];

    var distEl = document.getElementById('chartDistribution');
    if (distEl && Object.keys(distribution).length) {
        new Chart(distEl, {
            type: 'doughnut',
            data: {
                labels: Object.keys(distribution),
                datasets: [{
                    data: Object.values(distribution),
                    backgroundColor: palette,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
            }
        });
    }

    var trendEl = document.getElementById('chartTrend');
    if (trendEl) {
        new Chart(trendEl, {
            type: 'line',
            data: {
                labels: measurementTrend.labels,
                datasets: [
                    {
                        label: 'Pemeriksaan',
                        data: measurementTrend.values,
                        borderColor: '#0f766e',
                        backgroundColor: 'rgba(15,118,110,.12)',
                        fill: true,
                        tension: .35
                    },
                    {
                        label: 'Prediksi',
                        data: predictionTrend.values,
                        borderColor: '#d97706',
                        backgroundColor: 'rgba(217,119,6,.10)',
                        fill: true,
                        tension: .35
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
            }
        });
    }
})();
</script>
@endpush
