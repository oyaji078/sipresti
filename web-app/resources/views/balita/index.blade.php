@extends('layouts.app')

@section('title', 'Data Balita')
@section('page-title', 'Data Balita')
@section('page-subtitle', 'Daftar balita di wilayah kerja ' . config('sipresti.instansi') . '.')

@section('page-actions')
    @if (auth()->user()->isPetugasGizi())
        <a href="{{ route('balita.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Tambah Balita
        </a>
    @endif
@endsection

@section('content')
<div class="card mb-3 sip-no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('balita.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Cari</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nama, kode, NIK, nama ibu">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Jenis Kelamin</label>
                <select name="gender" class="form-select">
                    <option value="">Semua</option>
                    <option value="L" @selected(($filters['gender'] ?? '') === 'L')>Laki-laki</option>
                    <option value="P" @selected(($filters['gender'] ?? '') === 'P')>Perempuan</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Desa</label>
                <select name="village" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($villages as $village)
                        <option value="{{ $village }}" @selected(($filters['village'] ?? '') === $village)>{{ $village }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Posyandu</label>
                <select name="posyandu" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($posyandus as $posyandu)
                        <option value="{{ $posyandu }}" @selected(($filters['posyandu'] ?? '') === $posyandu)>{{ $posyandu }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('balita.index') }}" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Menampilkan {{ $toddlers->count() }} dari {{ number_format($toddlers->total(), 0, ',', '.') }} balita</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>NIK</th>
                    <th>JK</th>
                    <th>Tgl Lahir</th>
                    <th>Umur</th>
                    <th>Desa</th>
                    <th>Posyandu</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($toddlers as $toddler)
                    <tr>
                        <td><span class="badge text-bg-light border font-monospace">{{ $toddler->code }}</span></td>
                        <td>
                            <a href="{{ route('balita.show', $toddler) }}" class="text-decoration-none fw-semibold">{{ $toddler->name }}</a>
                            @if ($toddler->latestPrediction)
                                <div class="small">
                                    <span class="text-{{ $toddler->latestPrediction->statusClass() }}">
                                        <i class="bi {{ $toddler->latestPrediction->statusIcon() }}"></i>
                                        {{ $toddler->latestPrediction->statusLabel() }}
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td class="font-monospace small">{{ $toddler->maskedNik() }}</td>
                        <td>{{ $toddler->gender }}</td>
                        <td class="small">{{ $toddler->birth_date->format('d/m/Y') }}</td>
                        <td class="small">{{ $toddler->ageLabel() }}</td>
                        <td class="small">{{ $toddler->village ?? '-' }}</td>
                        <td class="small">{{ $toddler->posyandu ?? '-' }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('balita.show', $toddler) }}" class="btn btn-outline-secondary" title="Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if (auth()->user()->isPetugasGizi())
                                    <a href="{{ route('balita.edit', $toddler) }}" class="btn btn-outline-secondary" title="Ubah">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="sip-empty">
                                <i class="bi bi-people"></i>
                                <div class="fw-semibold mb-1">Belum ada data balita.</div>
                                <div class="small mb-3">Tambahkan data balita atau impor dari berkas E-PPBGM.</div>
                                @if (auth()->user()->isPetugasGizi())
                                    <a href="{{ route('balita.create') }}" class="btn btn-primary btn-sm">
                                        <i class="bi bi-plus-lg me-1"></i>Tambah Balita
                                    </a>
                                    <a href="{{ route('import.index') }}" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-upload me-1"></i>Import E-PPBGM
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($toddlers->hasPages())
        <div class="card-body border-top">{{ $toddlers->links() }}</div>
    @endif
</div>
@endsection
