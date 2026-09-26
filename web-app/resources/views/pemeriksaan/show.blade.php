@extends('layouts.app')

@section('title', 'Detail Pemeriksaan')
@section('page-title', 'Detail Pemeriksaan')
@section('page-subtitle', $measurement->toddler->name . ' — ' . $measurement->measurement_date->format('d F Y'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('pemeriksaan.index') }}">Pemeriksaan</a></li>
    <li class="breadcrumb-item active">Detail</li>
@endsection

@section('page-actions')
    @if (auth()->user()->isPetugasGizi())
        <div class="btn-group">
            <a href="{{ route('prediksi.create', ['pemeriksaan' => $measurement->id]) }}" class="btn btn-primary">
                <i class="bi bi-cpu me-1"></i>Analisis Risiko
            </a>
            <a href="{{ route('pemeriksaan.edit', $measurement) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil"></i>
            </a>
        </div>
    @endif
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-person-vcard me-1"></i>Balita</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Kode</dt>
                    <dd class="col-7"><span class="badge text-bg-light border font-monospace">{{ $measurement->toddler->code }}</span></dd>

                    <dt class="col-5 text-muted fw-normal">Nama</dt>
                    <dd class="col-7 fw-semibold">
                        <a href="{{ route('balita.show', $measurement->toddler_id) }}" class="text-decoration-none">{{ $measurement->toddler->name }}</a>
                    </dd>

                    <dt class="col-5 text-muted fw-normal">Jenis Kelamin</dt>
                    <dd class="col-7">{{ $measurement->toddler->genderLabel() }}</dd>

                    <dt class="col-5 text-muted fw-normal">Tanggal Lahir</dt>
                    <dd class="col-7">{{ $measurement->toddler->birth_date->format('d/m/Y') }}</dd>

                    <dt class="col-5 text-muted fw-normal">Posyandu</dt>
                    <dd class="col-7">{{ $measurement->toddler->posyandu ?? '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-rulers me-1"></i>Hasil Pengukuran</div>
            <div class="card-body">
                <div class="row g-3 text-center mb-3">
                    <div class="col-4">
                        <div class="sip-stat">
                            <div class="sip-stat__label">Usia</div>
                            <div class="sip-stat__value fs-4 mt-1">{{ $measurement->age_months }}</div>
                            <div class="small text-muted">bulan</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="sip-stat">
                            <div class="sip-stat__label">Berat</div>
                            <div class="sip-stat__value fs-4 mt-1">{{ $measurement->weight_kg }}</div>
                            <div class="small text-muted">kg</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="sip-stat">
                            <div class="sip-stat__label">Tinggi</div>
                            <div class="sip-stat__value fs-4 mt-1">{{ $measurement->height_cm }}</div>
                            <div class="small text-muted">cm</div>
                        </div>
                    </div>
                </div>

                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Tanggal Pemeriksaan</dt>
                    <dd class="col-7">{{ $measurement->measurement_date->format('d/m/Y') }}</dd>

                    <dt class="col-5 text-muted fw-normal">Sumber Data</dt>
                    <dd class="col-7"><span class="badge {{ $measurement->source->badgeClass() }}">{{ $measurement->source->label() }}</span></dd>

                    <dt class="col-5 text-muted fw-normal">Dicatat oleh</dt>
                    <dd class="col-7">{{ $measurement->creator?->name ?? '-' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Waktu Input</dt>
                    <dd class="col-7">{{ $measurement->created_at?->format('d/m/Y H:i') }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><i class="bi bi-cpu me-1"></i>Hasil Prediksi dari Pemeriksaan Ini</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Hasil</th>
                    <th class="text-end">Probabilitas</th>
                    <th>Model</th>
                    <th>Petugas</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($measurement->predictions as $p)
                    <tr>
                        <td class="small">{{ $p->predicted_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <span class="badge text-bg-{{ $p->statusClass() }}">
                                <i class="bi {{ $p->statusIcon() }} me-1"></i>{{ $p->statusLabel() }}
                            </span>
                        </td>
                        <td class="text-end fw-semibold">{{ $p->probabilityPercent() }}</td>
                        <td class="small font-monospace">{{ $p->modelVersion?->version ?? '-' }}</td>
                        <td class="small">{{ $p->predictor?->name ?? '-' }}</td>
                        <td class="text-end">
                            <a href="{{ route('prediksi.show', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="sip-empty">
                                <i class="bi bi-cpu"></i>
                                <div class="fw-semibold mb-1">Belum ada prediksi untuk pemeriksaan ini.</div>
                                @if (auth()->user()->isPetugasGizi())
                                    <a href="{{ route('prediksi.create', ['pemeriksaan' => $measurement->id]) }}" class="btn btn-sm btn-primary mt-2">
                                        <i class="bi bi-play-circle me-1"></i>Jalankan Analisis Risiko
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
