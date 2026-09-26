@extends('layouts.app')

@section('title', 'Pemeriksaan')
@section('page-title', 'Data Pemeriksaan Antropometri')
@section('page-subtitle', 'Riwayat penimbangan dan pengukuran balita.')

@section('page-actions')
    @if (auth()->user()->isPetugasGizi())
        <a href="{{ route('pemeriksaan.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Tambah Pemeriksaan
        </a>
    @endif
@endsection

@section('content')
<div class="card mb-3 sip-no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('pemeriksaan.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Cari Balita</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nama, kode, NIK">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Sumber</label>
                <select name="source" class="form-select">
                    <option value="">Semua</option>
                    <option value="MANUAL" @selected(($filters['source'] ?? '') === 'MANUAL')>Input Manual</option>
                    <option value="IMPORT_EPPBGM" @selected(($filters['source'] ?? '') === 'IMPORT_EPPBGM')>Import E-PPBGM</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('pemeriksaan.index') }}" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">Menampilkan {{ $measurements->count() }} dari {{ number_format($measurements->total(), 0, ',', '.') }} pemeriksaan</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Balita</th>
                    <th>JK</th>
                    <th>Usia</th>
                    <th>BB</th>
                    <th>TB</th>
                    <th>LiLA</th>
                    <th>Posyandu</th>
                    <th>Sumber</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($measurements as $m)
                    <tr>
                        <td class="small">{{ $m->measurement_date->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('balita.show', $m->toddler_id) }}" class="text-decoration-none fw-semibold">{{ $m->toddler->name }}</a>
                            <div class="text-muted small font-monospace">{{ $m->toddler->code }}</div>
                        </td>
                        <td>{{ $m->toddler->gender }}</td>
                        <td class="small">{{ $m->age_months }} bln</td>
                        <td class="small">{{ $m->weight_kg }} kg</td>
                        <td class="small">{{ $m->height_cm }} cm</td>
                        <td class="small">{{ $m->muac_cm ? $m->muac_cm.' cm' : '-' }}</td>
                        <td class="small">{{ $m->toddler->posyandu ?? '-' }}</td>
                        <td><span class="badge {{ $m->source->badgeClass() }}">{{ $m->source->label() }}</span></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('pemeriksaan.show', $m) }}" class="btn btn-outline-secondary" title="Detail"><i class="bi bi-eye"></i></a>
                                @if (auth()->user()->isPetugasGizi())
                                    <a href="{{ route('pemeriksaan.edit', $m) }}" class="btn btn-outline-secondary" title="Ubah"><i class="bi bi-pencil"></i></a>
                                    <a href="{{ route('prediksi.create', ['pemeriksaan' => $m->id]) }}" class="btn btn-outline-primary" title="Analisis Risiko"><i class="bi bi-cpu"></i></a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">
                            <div class="sip-empty">
                                <i class="bi bi-clipboard2-x"></i>
                                <div class="fw-semibold mb-1">Belum ada data pemeriksaan.</div>
                                @if (auth()->user()->isPetugasGizi())
                                    <a href="{{ route('pemeriksaan.create') }}" class="btn btn-sm btn-primary mt-2">
                                        <i class="bi bi-plus-lg me-1"></i>Tambah Pemeriksaan
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($measurements->hasPages())
        <div class="card-body border-top">{{ $measurements->links() }}</div>
    @endif
</div>
@endsection
