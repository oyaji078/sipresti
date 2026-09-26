@extends('layouts.app')

@section('title', 'Pengaturan')
@section('page-title', 'Pengaturan Sistem')
@section('page-subtitle', 'Identitas instansi dan keterangan laporan.')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')
            <div class="card">
                <div class="card-header"><i class="bi bi-sliders me-1"></i>Identitas &amp; Laporan</div>
                <div class="card-body">
                    @foreach ($fields as $key => $meta)
                        <div class="mb-3">
                            <label for="{{ $key }}" class="form-label fw-semibold">{{ $meta['label'] }}</label>
                            <input type="text"
                                   id="{{ $key }}"
                                   name="settings[{{ $key }}]"
                                   class="form-control @error('settings.'.$key) is-invalid @enderror"
                                   value="{{ old('settings.'.$key, $values[$key] ?? '') }}">
                            @error('settings.'.$key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="card-body border-top">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Pengaturan</button>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-shield-lock me-1"></i>Konfigurasi Layanan Prediksi</div>
            <div class="card-body">
                <p class="small text-muted">
                    URL dan kunci API layanan prediksi <strong>tidak dapat diubah dari antarmuka</strong>.
                    Keduanya hanya dibaca dari environment variable agar tidak pernah tersimpan di basis data
                    maupun terkirim ke peramban.
                </p>

                <dl class="row mb-0 small">
                    <dt class="col-6 text-muted fw-normal">ML_API_URL</dt>
                    <dd class="col-6">
                        <span class="badge {{ $mlUrlConfigured ? 'text-bg-success' : 'text-bg-danger' }}">
                            {{ $mlUrlConfigured ? 'Terisi' : 'Kosong' }}
                        </span>
                    </dd>

                    <dt class="col-6 text-muted fw-normal">ML_API_KEY</dt>
                    <dd class="col-6">
                        <span class="badge {{ $mlKeyConfigured ? 'text-bg-success' : 'text-bg-danger' }}">
                            {{ $mlKeyConfigured ? 'Terisi' : 'Kosong' }}
                        </span>
                    </dd>

                    <dt class="col-6 text-muted fw-normal">Status Layanan</dt>
                    <dd class="col-6">
                        <span class="badge {{ $health['reachable'] ? 'text-bg-success' : 'text-bg-danger' }}">
                            {{ $health['reachable'] ? 'Terhubung' : 'Tidak Terhubung' }}
                        </span>
                    </dd>

                    <dt class="col-6 text-muted fw-normal">Model Dimuat</dt>
                    <dd class="col-6">
                        <span class="badge {{ $health['model_loaded'] ? 'text-bg-success' : 'text-bg-warning' }}">
                            {{ $health['model_loaded'] ? 'Ya' : 'Belum' }}
                        </span>
                    </dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i>Catatan</div>
            <div class="card-body small text-muted">
                Nilai pengaturan di halaman ini dipakai pada kop dan tanda tangan halaman cetak laporan.
                Mengubahnya tidak memengaruhi data balita, pemeriksaan, maupun hasil prediksi.
            </div>
        </div>
    </div>
</div>
@endsection
