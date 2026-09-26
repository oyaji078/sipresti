@extends('layouts.app')

@section('title', 'Hasil Prediksi')
@section('page-title', 'Hasil Prediksi Risiko Stunting')
@section('page-subtitle', 'Keluaran model XGBoost untuk satu data pemeriksaan.')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('prediksi.index') }}">Prediksi</a></li>
    <li class="breadcrumb-item active">#{{ $prediction->id }}</li>
@endsection

@section('page-actions')
    <div class="btn-group sip-no-print">
        <a href="{{ route('balita.show', $prediction->toddler_id) }}" class="btn btn-outline-secondary">
            <i class="bi bi-person me-1"></i>Profil Balita
        </a>
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Cetak
        </button>
    </div>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="sip-result sip-result--{{ $prediction->statusClass() }} mb-3">
            <div class="sip-result__icon text-{{ $prediction->statusClass() }}">
                <i class="bi {{ $prediction->statusIcon() }}"></i>
            </div>
            <div class="text-uppercase small fw-semibold text-muted">Prediksi Model</div>
            <div class="sip-result__status text-{{ $prediction->statusClass() }}">{{ $prediction->statusLabel() }}</div>
            <div class="badge text-bg-light border font-monospace">{{ $prediction->prediction_label }}</div>

            <hr>

            <div class="text-uppercase small fw-semibold text-muted">Probabilitas Keluaran Model</div>
            <div class="sip-result__prob text-{{ $prediction->statusClass() }}">{{ $prediction->probabilityPercent() }}</div>
            <div class="small text-muted">kelas model: <span class="font-monospace">{{ $prediction->prediction_class }}</span></div>
        </div>

        <div class="sip-disclaimer">
            <i class="bi bi-exclamation-circle me-1"></i>
            {{ config('sipresti.disclaimer') }}
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person-vcard me-1"></i>Identitas Balita</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4 text-muted fw-normal">Nama</dt>
                    <dd class="col-8 fw-semibold">
                        <a href="{{ route('balita.show', $prediction->toddler_id) }}" class="text-decoration-none">{{ $prediction->toddler->name }}</a>
                    </dd>

                    <dt class="col-4 text-muted fw-normal">Kode Balita</dt>
                    <dd class="col-8"><span class="badge text-bg-light border font-monospace">{{ $prediction->toddler->code }}</span></dd>

                    <dt class="col-4 text-muted fw-normal">Jenis Kelamin</dt>
                    <dd class="col-8">{{ $prediction->toddler->genderLabel() }}</dd>

                    <dt class="col-4 text-muted fw-normal">Posyandu</dt>
                    <dd class="col-8">{{ $prediction->toddler->posyandu ?? '-' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-rulers me-1"></i>Data Pengukuran yang Dianalisis</div>
            <div class="card-body">
                @php
                    $snapshot = $prediction->input_snapshot_json ?? [];
                @endphp
                <div class="row g-3 text-center">
                    <div class="col-3">
                        <div class="sip-stat">
                            <div class="sip-stat__label">JK</div>
                            <div class="fw-bold fs-5 mt-1">{{ $snapshot['gender'] ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="sip-stat">
                            <div class="sip-stat__label">Usia</div>
                            <div class="fw-bold fs-5 mt-1">{{ $snapshot['age_months'] ?? '-' }}</div>
                            <div class="small text-muted">bulan</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="sip-stat">
                            <div class="sip-stat__label">Berat</div>
                            <div class="fw-bold fs-5 mt-1">{{ $snapshot['weight_kg'] ?? '-' }}</div>
                            <div class="small text-muted">kg</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="sip-stat">
                            <div class="sip-stat__label">Tinggi</div>
                            <div class="fw-bold fs-5 mt-1">{{ $snapshot['height_cm'] ?? '-' }}</div>
                            <div class="small text-muted">cm</div>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mb-0 mt-3">
                    Nilai di atas adalah salinan data saat prediksi dijalankan, sehingga hasil ini tetap dapat
                    ditelusuri meskipun data pemeriksaan diperbarui kemudian.
                    @if ($prediction->measurement)
                        <a href="{{ route('pemeriksaan.show', $prediction->measurement_id) }}">Lihat data pemeriksaan</a>.
                    @endif
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i>Informasi Prediksi</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4 text-muted fw-normal">Versi Model</dt>
                    <dd class="col-8 font-monospace">{{ $prediction->modelVersion?->version ?? '-' }}</dd>

                    <dt class="col-4 text-muted fw-normal">Algoritma</dt>
                    <dd class="col-8">{{ $prediction->modelVersion?->algorithm ?? 'XGBoost' }}</dd>

                    <dt class="col-4 text-muted fw-normal">Tanggal Prediksi</dt>
                    <dd class="col-8">{{ $prediction->predicted_at->format('d/m/Y H:i') }} WITA</dd>

                    <dt class="col-4 text-muted fw-normal">Petugas</dt>
                    <dd class="col-8">{{ $prediction->predictor?->name ?? '-' }}</dd>

                    <dt class="col-4 text-muted fw-normal">ID Permintaan</dt>
                    <dd class="col-8 font-monospace small">{{ $prediction->request_id }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><i class="bi bi-clipboard2-heart me-1"></i>Tindak Lanjut</div>
    <div class="card-body">
        <p class="mb-2 fw-semibold">Perlu evaluasi lebih lanjut oleh Petugas Gizi.</p>
        <p class="text-muted small mb-0">
            Rekomendasi intervensi gizi terperinci belum ditampilkan karena aturan klinisnya belum divalidasi
            oleh tenaga gizi berwenang. Sistem sengaja tidak menyusun sendiri anjuran medis.
        </p>
    </div>
</div>
@endsection
