@extends('layouts.app')

@section('title', 'Detail Balita')
@section('page-title', $toddler->name)
@section('page-subtitle', $toddler->code . ' — ' . $toddler->genderLabel() . ', ' . $toddler->ageLabel())

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('balita.index') }}">Data Balita</a></li>
    <li class="breadcrumb-item active">{{ $toddler->code }}</li>
@endsection

@section('page-actions')
    @if (auth()->user()->isPetugasGizi())
        <div class="btn-group">
            <a href="{{ route('pemeriksaan.create', ['balita' => $toddler->id]) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Pemeriksaan
            </a>
            <a href="{{ route('prediksi.create', ['balita' => $toddler->id]) }}" class="btn btn-outline-primary">
                <i class="bi bi-cpu me-1"></i>Analisis Risiko
            </a>
            <a href="{{ route('balita.edit', $toddler) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil"></i>
            </a>
        </div>
    @endif
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-person-vcard me-1"></i>Identitas</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Kode</dt>
                    <dd class="col-7"><span class="badge text-bg-light border font-monospace">{{ $toddler->code }}</span></dd>

                    <dt class="col-5 text-muted fw-normal">Nama</dt>
                    <dd class="col-7 fw-semibold">{{ $toddler->name }}</dd>

                    <dt class="col-5 text-muted fw-normal">NIK</dt>
                    <dd class="col-7 font-monospace">{{ $toddler->maskedNik() }}</dd>

                    <dt class="col-5 text-muted fw-normal">Jenis Kelamin</dt>
                    <dd class="col-7">{{ $toddler->genderLabel() }}</dd>

                    <dt class="col-5 text-muted fw-normal">Tanggal Lahir</dt>
                    <dd class="col-7">{{ $toddler->birth_date->format('d/m/Y') }}</dd>

                    <dt class="col-5 text-muted fw-normal">Umur Saat Ini</dt>
                    <dd class="col-7">{{ $toddler->ageLabel() }}</dd>

                    <dt class="col-5 text-muted fw-normal">Nama Ibu</dt>
                    <dd class="col-7">{{ $toddler->mother_name ?? '-' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Alamat</dt>
                    <dd class="col-7">{{ $toddler->address ?? '-' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Desa</dt>
                    <dd class="col-7">{{ $toddler->village ?? '-' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Posyandu</dt>
                    <dd class="col-7">{{ $toddler->posyandu ?? '-' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Dicatat oleh</dt>
                    <dd class="col-7">{{ $toddler->creator?->name ?? '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clipboard2-pulse me-1"></i>Pemeriksaan Terakhir</div>
            <div class="card-body">
                @if ($latest)
                    <div class="row g-3 text-center">
                        <div class="col-6 col-md-3">
                            <div class="sip-stat">
                                <div class="sip-stat__label">Tanggal</div>
                                <div class="fw-bold fs-6 mt-1">{{ $latest->measurement_date->format('d/m/Y') }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="sip-stat">
                                <div class="sip-stat__label">Usia</div>
                                <div class="fw-bold fs-6 mt-1">{{ $latest->age_months }} bulan</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="sip-stat">
                                <div class="sip-stat__label">Berat</div>
                                <div class="fw-bold fs-6 mt-1">{{ $latest->weight_kg }} kg</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="sip-stat">
                                <div class="sip-stat__label">Tinggi</div>
                                <div class="fw-bold fs-6 mt-1">{{ $latest->height_cm }} cm</div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="sip-empty">
                        <i class="bi bi-clipboard2-x"></i>
                        <div class="fw-semibold mb-1">Belum ada data pemeriksaan.</div>
                        @if (auth()->user()->isPetugasGizi())
                            <a href="{{ route('pemeriksaan.create', ['balita' => $toddler->id]) }}" class="btn btn-sm btn-primary mt-2">
                                <i class="bi bi-plus-lg me-1"></i>Tambah Pemeriksaan
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-graph-up-arrow me-1"></i>Grafik Pertumbuhan</div>
            <div class="card-body">
                @if (count($growth['labels']) > 0)
                    <canvas id="chartGrowth" height="150"></canvas>
                    <p class="text-muted small mb-0 mt-2">
                        Grafik menampilkan hasil pengukuran yang tercatat pada sistem. Perbandingan terhadap kurva standar
                        pertumbuhan belum ditampilkan karena tabel rujukan resmi belum tersedia di sistem.
                    </p>
                @else
                    <div class="sip-empty"><i class="bi bi-graph-up"></i>Grafik tampil setelah ada data pemeriksaan.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-list-ul me-1"></i>Histori Pemeriksaan</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Usia</th>
                            <th>BB</th>
                            <th>TB</th>
                            <th>Sumber</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($measurements as $m)
                            <tr>
                                <td class="small">{{ $m->measurement_date->format('d/m/Y') }}</td>
                                <td class="small">{{ $m->age_months }} bln</td>
                                <td class="small">{{ $m->weight_kg }} kg</td>
                                <td class="small">{{ $m->height_cm }} cm</td>
                                <td><span class="badge {{ $m->source->badgeClass() }}">{{ $m->source->label() }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('pemeriksaan.show', $m) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="sip-empty"><i class="bi bi-clipboard2-x"></i>Belum ada pemeriksaan.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($measurements->hasPages())
                <div class="card-body border-top">{{ $measurements->links() }}</div>
            @endif
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-cpu me-1"></i>Histori Prediksi</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Hasil</th>
                            <th class="text-end">Prob.</th>
                            <th>Model</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($predictions as $p)
                            <tr>
                                <td class="small">{{ $p->predicted_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $p->statusClass() }}">
                                        <i class="bi {{ $p->statusIcon() }} me-1"></i>{{ $p->statusLabel() }}
                                    </span>
                                </td>
                                <td class="text-end fw-semibold small">{{ $p->probabilityPercent() }}</td>
                                <td class="small font-monospace">{{ $p->modelVersion?->version ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('prediksi.show', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="sip-empty"><i class="bi bi-cpu"></i>Belum ada hasil prediksi.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($predictions->hasPages())
                <div class="card-body border-top">{{ $predictions->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var growth = @json($growth);
    var el = document.getElementById('chartGrowth');
    if (!el || !growth.labels.length) return;

    new Chart(el, {
        type: 'line',
        data: {
            labels: growth.labels,
            datasets: [
                {
                    label: 'Berat Badan (kg)',
                    data: growth.weight,
                    borderColor: '#0f766e',
                    backgroundColor: 'rgba(15,118,110,.12)',
                    yAxisID: 'y',
                    tension: .3
                },
                {
                    label: 'Tinggi/Panjang Badan (cm)',
                    data: growth.height,
                    borderColor: '#d97706',
                    backgroundColor: 'rgba(217,119,6,.12)',
                    yAxisID: 'y1',
                    tension: .3
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { position: 'left', title: { display: true, text: 'kg' } },
                y1: { position: 'right', title: { display: true, text: 'cm' }, grid: { drawOnChartArea: false } }
            },
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
        }
    });
})();
</script>
@endpush
