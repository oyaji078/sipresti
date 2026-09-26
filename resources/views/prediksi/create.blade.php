@extends('layouts.app')

@section('title', 'Prediksi Risiko Stunting')
@section('page-title', 'Prediksi Risiko Stunting')
@section('page-subtitle', 'Pilih balita dan data pemeriksaan, lalu jalankan analisis dengan model XGBoost.')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('prediksi.index') }}">Prediksi</a></li>
    <li class="breadcrumb-item active">Analisis Baru</li>
@endsection

@section('content')

@if (! $health['reachable'])
    <div class="alert alert-danger">
        <i class="bi bi-plug me-1"></i>
        <strong>Layanan prediksi tidak dapat dihubungi.</strong> {{ $health['message'] }}
    </div>
@elseif (! $health['model_loaded'])
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>Model prediksi belum tersedia.</strong> {{ $health['message'] }}
        Analisis belum dapat dijalankan sampai artifact model dimuat pada ML API.
    </div>
@else
    <div class="alert alert-success py-2">
        <i class="bi bi-check-circle me-1"></i>
        Layanan prediksi aktif &mdash; model <span class="font-monospace fw-semibold">{{ $health['model_version'] ?? '-' }}</span>.
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-1-circle me-1"></i>Pilih Balita &amp; Pemeriksaan</div>
            <div class="card-body">
                <form method="GET" action="{{ route('prediksi.create') }}">
                    <div class="mb-3">
                        <label for="balita" class="form-label fw-semibold">Balita</label>
                        <select id="balita" name="balita" class="form-select" data-auto-submit>
                            <option value="">— Pilih balita —</option>
                            @foreach ($toddlers as $t)
                                <option value="{{ $t->id }}" @selected($toddler?->id === $t->id)>
                                    {{ $t->code }} — {{ $t->name }} ({{ $t->gender }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <noscript><button type="submit" class="btn btn-outline-primary btn-sm">Muat Pemeriksaan</button></noscript>
                </form>

                @if ($toddler)
                    <hr>
                    <form method="GET" action="{{ route('prediksi.create') }}">
                        <input type="hidden" name="balita" value="{{ $toddler->id }}">
                        <label for="pemeriksaan" class="form-label fw-semibold">Data Pemeriksaan</label>
                        <select id="pemeriksaan" name="pemeriksaan" class="form-select" data-auto-submit>
                            <option value="">— Pilih pemeriksaan —</option>
                            @foreach ($measurements as $m)
                                <option value="{{ $m->id }}" @selected($measurement?->id === $m->id)>
                                    {{ $m->measurement_date->format('d/m/Y') }} — {{ $m->age_months }} bln, {{ $m->weight_kg }} kg, {{ $m->height_cm }} cm
                                </option>
                            @endforeach
                        </select>
                        @if ($measurements->isEmpty())
                            <div class="form-text text-danger">
                                Balita ini belum punya data pemeriksaan.
                                <a href="{{ route('pemeriksaan.create', ['balita' => $toddler->id]) }}">Tambahkan sekarang</a>.
                            </div>
                        @endif
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-2-circle me-1"></i>Data yang Dianalisis</div>

            @if ($measurement)
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small text-uppercase fw-semibold mb-2">Informasi Balita</div>
                            <dl class="row mb-0 small">
                                <dt class="col-5 text-muted fw-normal">Nama</dt>
                                <dd class="col-7 fw-semibold">{{ $measurement->toddler->name }}</dd>
                                <dt class="col-5 text-muted fw-normal">Kode</dt>
                                <dd class="col-7 font-monospace">{{ $measurement->toddler->code }}</dd>
                                <dt class="col-5 text-muted fw-normal">Jenis Kelamin</dt>
                                <dd class="col-7">{{ $measurement->toddler->genderLabel() }}</dd>
                                <dt class="col-5 text-muted fw-normal">Tanggal Lahir</dt>
                                <dd class="col-7">{{ $measurement->toddler->birth_date->format('d/m/Y') }}</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small text-uppercase fw-semibold mb-2">Data Pengukuran</div>
                            <dl class="row mb-0 small">
                                <dt class="col-5 text-muted fw-normal">Tanggal</dt>
                                <dd class="col-7">{{ $measurement->measurement_date->format('d/m/Y') }}</dd>
                                <dt class="col-5 text-muted fw-normal">Usia</dt>
                                <dd class="col-7 fw-semibold">{{ $measurement->age_months }} bulan</dd>
                                <dt class="col-5 text-muted fw-normal">Berat Badan</dt>
                                <dd class="col-7 fw-semibold">{{ $measurement->weight_kg }} kg</dd>
                                <dt class="col-5 text-muted fw-normal">Tinggi/Panjang</dt>
                                <dd class="col-7 fw-semibold">{{ $measurement->height_cm }} cm</dd>
                            </dl>
                        </div>
                    </div>

                    <div class="sip-disclaimer mt-3">
                        <i class="bi bi-shield-lock me-1"></i>
                        Hanya empat data di atas (jenis kelamin, usia, berat, tinggi) yang dikirim ke layanan model.
                        Nama, NIK, alamat, dan nama ibu tidak pernah dikirim keluar.
                    </div>
                </div>

                <div class="card-body border-top">
                    <form method="POST" action="{{ route('prediksi.store') }}" data-loading="MENGANALISIS...">
                        @csrf
                        <input type="hidden" name="measurement_id" value="{{ $measurement->id }}">
                        <button type="submit" class="btn btn-primary btn-lg w-100"
                                @disabled(! $health['reachable'] || ! $health['model_loaded'])>
                            <i class="bi bi-play-circle me-1"></i>ANALISIS RISIKO
                        </button>
                    </form>

                    @if (! $health['reachable'] || ! $health['model_loaded'])
                        <p class="text-center text-muted small mb-0 mt-2">
                            Tombol nonaktif karena layanan prediksi belum siap.
                        </p>
                    @endif
                </div>
            @else
                <div class="card-body">
                    <div class="sip-empty">
                        <i class="bi bi-clipboard2-data"></i>
                        <div class="fw-semibold mb-1">Belum ada data yang dipilih.</div>
                        <div class="small">Pilih balita lalu pilih data pemeriksaan di panel sebelah kiri.</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<p class="sip-disclaimer mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>{{ config('sipresti.disclaimer') }}
</p>
@endsection
