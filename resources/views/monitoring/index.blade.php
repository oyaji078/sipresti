@extends('layouts.app')

@section('title', 'Monitoring')
@section('page-title', 'Monitoring Risiko Stunting')
@section('page-subtitle', 'Pemetaan hasil prediksi per wilayah dan periode.')

@section('content')
<div class="card mb-3 sip-no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('monitoring.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Cari Balita</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nama / kode">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Desa</label>
                <select name="village" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($villages as $v)
                        <option value="{{ $v }}" @selected(($filters['village'] ?? '') === $v)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Posyandu</label>
                <select name="posyandu" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($posyandus as $p)
                        <option value="{{ $p }}" @selected(($filters['posyandu'] ?? '') === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Hasil</label>
                <select name="result" class="form-select">
                    <option value="">Semua</option>
                    <option value="risiko" @selected(($filters['result'] ?? '') === 'risiko')>Berisiko</option>
                    <option value="tidak" @selected(($filters['result'] ?? '') === 'tidak')>Tidak Berisiko</option>
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label small fw-semibold mb-1">Dari</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label small fw-semibold mb-1">Sampai</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-12 col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel"></i></button>
                <a href="{{ route('monitoring.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    @php
        $tiles = [
            ['Total Prediksi', $summary['total'], 'sip-soft-primary', 'bi-cpu'],
            ['Berisiko', $summary['at_risk'], 'sip-soft-danger', 'bi-exclamation-triangle'],
            ['Tidak Berisiko', $summary['not_at_risk'], 'sip-soft-success', 'bi-shield-check'],
        ];
    @endphp
    @foreach ($tiles as [$label, $value, $tone, $icon])
        <div class="col-md-4">
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

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart me-1"></i>Sebaran Hasil per Posyandu</div>
            <div class="card-body">
                @if (count($byPosyandu) > 0)
                    <canvas id="chartPosyandu" height="200"></canvas>
                @else
                    <div class="sip-empty"><i class="bi bi-bar-chart"></i>Belum ada data untuk filter ini.</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart me-1"></i>Distribusi Label Model</div>
            <div class="card-body">
                @if (count($distribution) > 0)
                    <canvas id="chartLabels" height="200"></canvas>
                @else
                    <div class="sip-empty"><i class="bi bi-pie-chart"></i>Belum ada hasil prediksi.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Detail Hasil ({{ number_format($predictions->total(), 0, ',', '.') }})</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Tanggal</th><th>Balita</th><th>Desa</th><th>Posyandu</th><th>Usia</th><th>BB</th><th>TB</th><th>Hasil</th><th class="text-end">Prob.</th><th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($predictions as $p)
                    <tr>
                        <td class="small">{{ $p->predicted_at->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('balita.show', $p->toddler_id) }}" class="text-decoration-none fw-semibold">{{ $p->toddler->name }}</a>
                            <div class="text-muted small font-monospace">{{ $p->toddler->code }}</div>
                        </td>
                        <td class="small">{{ $p->toddler->village ?? '-' }}</td>
                        <td class="small">{{ $p->toddler->posyandu ?? '-' }}</td>
                        <td class="small">{{ $p->measurement?->age_months ?? '-' }} bln</td>
                        <td class="small">{{ $p->measurement?->weight_kg ?? '-' }}</td>
                        <td class="small">{{ $p->measurement?->height_cm ?? '-' }}</td>
                        <td>
                            <span class="badge text-bg-{{ $p->statusClass() }}">
                                <i class="bi {{ $p->statusIcon() }} me-1"></i>{{ $p->statusLabel() }}
                            </span>
                        </td>
                        <td class="text-end fw-semibold small">{{ $p->probabilityPercent() }}</td>
                        <td class="text-end"><a href="{{ route('prediksi.show', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="10"><div class="sip-empty"><i class="bi bi-search"></i>Tidak ada data yang cocok dengan filter.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($predictions->hasPages())
        <div class="card-body border-top">{{ $predictions->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    var byPosyandu = @json($byPosyandu);
    var distribution = @json($distribution);

    var el1 = document.getElementById('chartPosyandu');
    if (el1 && Object.keys(byPosyandu).length) {
        new Chart(el1, {
            type: 'bar',
            data: {
                labels: Object.keys(byPosyandu),
                datasets: [{ label: 'Jumlah Prediksi', data: Object.values(byPosyandu), backgroundColor: '#0f766e', borderRadius: 6 }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    var el2 = document.getElementById('chartLabels');
    if (el2 && Object.keys(distribution).length) {
        new Chart(el2, {
            type: 'pie',
            data: {
                labels: Object.keys(distribution),
                datasets: [{ data: Object.values(distribution), backgroundColor: ['#dc2626', '#16a34a', '#0284c7', '#d97706', '#7c3aed'], borderColor: '#fff', borderWidth: 2 }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
        });
    }
})();
</script>
@endpush
