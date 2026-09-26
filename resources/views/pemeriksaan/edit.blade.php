@extends('layouts.app')

@section('title', 'Ubah Pemeriksaan')
@section('page-title', 'Ubah Data Pemeriksaan')
@section('page-subtitle', $measurement->toddler->code . ' — ' . $measurement->toddler->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('pemeriksaan.index') }}">Pemeriksaan</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pemeriksaan.show', $measurement) }}">Detail</a></li>
    <li class="breadcrumb-item active">Ubah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-8">
        @if ($measurement->hasPrediction())
            <div class="alert alert-warning">
                <i class="bi bi-info-circle me-1"></i>
                Pemeriksaan ini sudah memiliki hasil prediksi. Mengubah data di sini <strong>tidak mengubah</strong>
                hasil prediksi yang sudah tersimpan, karena setiap prediksi menyimpan salinan data yang dipakainya.
            </div>
        @endif

        <form method="POST" action="{{ route('pemeriksaan.update', $measurement) }}">
            @csrf
            @method('PUT')
            <div class="card">
                <div class="card-header"><i class="bi bi-pencil-square me-1"></i>Data Pemeriksaan</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Balita</label>
                        <input type="text" class="form-control bg-light" readonly
                               value="{{ $measurement->toddler->code }} — {{ $measurement->toddler->name }}">
                        <div class="form-text">Balita tidak dapat diubah. Hapus data ini bila salah balita.</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="measurement_date" class="form-label fw-semibold sip-required">Tanggal Pemeriksaan</label>
                            <input type="date" id="measurement_date" name="measurement_date" max="{{ now()->toDateString() }}"
                                   class="form-control @error('measurement_date') is-invalid @enderror"
                                   value="{{ old('measurement_date', $measurement->measurement_date->toDateString()) }}" required>
                            @error('measurement_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Usia Tersimpan</label>
                            <input type="text" class="form-control bg-light" readonly value="{{ $measurement->age_months }} bulan">
                            <div class="form-text">Dihitung ulang otomatis saat disimpan.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="weight_kg" class="form-label fw-semibold sip-required">Berat Badan (kg)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" id="weight_kg" name="weight_kg"
                                       class="form-control @error('weight_kg') is-invalid @enderror"
                                       value="{{ old('weight_kg', $measurement->weight_kg) }}" required>
                                <span class="input-group-text">kg</span>
                                @error('weight_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="height_cm" class="form-label fw-semibold sip-required">Tinggi / Panjang Badan (cm)</label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0" id="height_cm" name="height_cm"
                                       class="form-control @error('height_cm') is-invalid @enderror"
                                       value="{{ old('height_cm', $measurement->height_cm) }}" required>
                                <span class="input-group-text">cm</span>
                                @error('height_cm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
                    <a href="{{ route('pemeriksaan.show', $measurement) }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </form>

        <div class="card mt-3 border-danger-subtle">
            <div class="card-header text-danger"><i class="bi bi-trash me-1"></i>Hapus Data</div>
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="small text-muted">Pemeriksaan yang sudah memiliki hasil prediksi tidak dapat dihapus.</div>
                <form method="POST" action="{{ route('pemeriksaan.destroy', $measurement) }}"
                      data-confirm="Hapus data pemeriksaan tanggal {{ $measurement->measurement_date->format('d/m/Y') }}?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
