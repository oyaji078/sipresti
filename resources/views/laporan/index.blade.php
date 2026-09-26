@extends('layouts.app')

@section('title', 'Laporan')
@section('page-title', 'Laporan')
@section('page-subtitle', 'Hasil cetak dan ekspor selalu mengikuti filter yang sedang aktif.')

@section('page-actions')
    <div class="btn-group sip-no-print">
        <a href="{{ route('laporan.print', request()->query()) }}" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Cetak / PDF
        </a>
        <a href="{{ route('laporan.export', request()->query()) }}" class="btn btn-outline-primary">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Ekspor CSV
        </a>
    </div>
@endsection

@section('content')
<div class="card mb-3 sip-no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('laporan.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Jenis Laporan</label>
                <select name="jenis" class="form-select">
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Tanggal Awal</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Tanggal Akhir</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-4 col-md-1">
                <label class="form-label small fw-semibold mb-1">JK</label>
                <select name="gender" class="form-select">
                    <option value="">Semua</option>
                    <option value="L" @selected(($filters['gender'] ?? '') === 'L')>L</option>
                    <option value="P" @selected(($filters['gender'] ?? '') === 'P')>P</option>
                </select>
            </div>
            <div class="col-4 col-md-2">
                <label class="form-label small fw-semibold mb-1">Desa</label>
                <select name="village" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($villages as $v)
                        <option value="{{ $v }}" @selected(($filters['village'] ?? '') === $v)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-4 col-md-2">
                <label class="form-label small fw-semibold mb-1">Posyandu</label>
                <select name="posyandu" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($posyandus as $p)
                        <option value="{{ $p }}" @selected(($filters['posyandu'] ?? '') === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </div>

            @if ($type === 'prediksi')
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold mb-1">Hasil Prediksi</label>
                    <select name="result" class="form-select">
                        <option value="">Semua</option>
                        <option value="risiko" @selected(($filters['result'] ?? '') === 'risiko')>Berisiko</option>
                        <option value="tidak" @selected(($filters['result'] ?? '') === 'tidak')>Tidak Berisiko</option>
                    </select>
                </div>
            @endif

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan Filter</button>
                <a href="{{ route('laporan.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>{{ $types[$type] }}</span>
        <span class="badge text-bg-light border">{{ number_format($rows->total(), 0, ',', '.') }} baris</span>
    </div>
    <div class="table-responsive">
        @include('laporan._table', ['type' => $type, 'rows' => $rows])
    </div>
    @if ($rows->hasPages())
        <div class="card-body border-top">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
