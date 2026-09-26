@extends('layouts.app')

@section('title', 'Histori Prediksi')
@section('page-title', 'Histori Prediksi')
@section('page-subtitle', 'Seluruh hasil analisis risiko yang pernah dijalankan.')

@section('page-actions')
    @if (auth()->user()->isPetugasGizi())
        <a href="{{ route('prediksi.create') }}" class="btn btn-primary">
            <i class="bi bi-play-circle me-1"></i>Analisis Baru
        </a>
    @endif
@endsection

@section('content')
<div class="card mb-3 sip-no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('prediksi.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Cari Balita</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nama, kode, NIK">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Hasil</label>
                <select name="result" class="form-select">
                    <option value="">Semua</option>
                    <option value="risiko" @selected(($filters['result'] ?? '') === 'risiko')>Berisiko</option>
                    <option value="tidak" @selected(($filters['result'] ?? '') === 'tidak')>Tidak Berisiko</option>
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
                <a href="{{ route('prediksi.index') }}" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">Menampilkan {{ $predictions->count() }} dari {{ number_format($predictions->total(), 0, ',', '.') }} prediksi</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Balita</th>
                    <th>Usia</th>
                    <th>BB</th>
                    <th>TB</th>
                    <th>Hasil</th>
                    <th class="text-end">Prob.</th>
                    <th>Model</th>
                    <th>Petugas</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($predictions as $p)
                    <tr>
                        <td class="small">{{ $p->predicted_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <a href="{{ route('balita.show', $p->toddler_id) }}" class="text-decoration-none fw-semibold">{{ $p->toddler->name }}</a>
                            <div class="text-muted small font-monospace">{{ $p->toddler->code }}</div>
                        </td>
                        <td class="small">{{ $p->measurement?->age_months ?? '-' }} bln</td>
                        <td class="small">{{ $p->measurement?->weight_kg ?? '-' }} kg</td>
                        <td class="small">{{ $p->measurement?->height_cm ?? '-' }} cm</td>
                        <td>
                            <span class="badge text-bg-{{ $p->statusClass() }}">
                                <i class="bi {{ $p->statusIcon() }} me-1"></i>{{ $p->statusLabel() }}
                            </span>
                        </td>
                        <td class="text-end fw-semibold small">{{ $p->probabilityPercent() }}</td>
                        <td class="small font-monospace">{{ $p->modelVersion?->version ?? '-' }}</td>
                        <td class="small">{{ $p->predictor?->name ?? '-' }}</td>
                        <td class="text-end">
                            <a href="{{ route('prediksi.show', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">
                            <div class="sip-empty">
                                <i class="bi bi-cpu"></i>
                                <div class="fw-semibold mb-1">Belum ada hasil prediksi.</div>
                                <div class="small mb-3">Hasil akan muncul setelah analisis risiko dijalankan.</div>
                                @if (auth()->user()->isPetugasGizi())
                                    <a href="{{ route('prediksi.create') }}" class="btn btn-sm btn-primary">
                                        <i class="bi bi-play-circle me-1"></i>Analisis Baru
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($predictions->hasPages())
        <div class="card-body border-top">{{ $predictions->links() }}</div>
    @endif
</div>
@endsection
