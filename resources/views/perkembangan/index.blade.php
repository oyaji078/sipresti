@extends('layouts.app')

@section('title', 'Perkembangan Balita')
@section('page-title', 'Perkembangan Balita')
@section('page-subtitle', 'Pemeriksaan terakhir dibandingkan dengan pemeriksaan sebelumnya untuk setiap balita.')

@php
    $delta = function (?float $value, string $unit): string {
        if ($value === null) {
            return '';
        }
        $sign = $value > 0 ? '+' : '';
        $class = $value > 0 ? 'text-success' : ($value < 0 ? 'text-danger' : 'text-muted');

        return '<div class="small '.$class.'">'.$sign.rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',').' '.$unit.'</div>';
    };
    $weightBadges = [
        'naik' => ['BB Naik', 'text-bg-success'],
        'tetap' => ['BB Tetap', 'text-bg-warning'],
        'turun' => ['BB Turun', 'text-bg-danger'],
    ];
    $trendBadges = [
        'memburuk' => ['Memburuk', 'text-bg-danger', 'bi-arrow-down-right'],
        'membaik' => ['Membaik', 'text-bg-success', 'bi-arrow-up-right'],
        'tetap' => ['Tetap', 'text-bg-light border', 'bi-arrow-right'],
    ];
@endphp

@section('content')
<div class="row g-3 mb-3">
    @foreach ([
        ['Balita', $summary['toddlers'], ''],
        ['Punya ≥ 2 Pemeriksaan', $summary['compared'], ''],
        ['BB Naik', $summary['weight_up'], 'text-success'],
        ['BB Tidak Naik', $summary['weight_not_up'], 'text-warning'],
        ['Risiko Memburuk', $summary['worse'], 'text-danger'],
        ['Berisiko (Terakhir)', $summary['at_risk'], 'text-danger'],
    ] as [$label, $value, $tone])
        <div class="col-6 col-md-4 col-xl-2">
            <div class="sip-stat text-center">
                <div class="sip-stat__value {{ $tone }}">{{ number_format($value, 0, ',', '.') }}</div>
                <div class="sip-stat__label">{{ $label }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="card mb-3 sip-no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('perkembangan.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Cari Balita</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nama, kode, NIK, nama ibu">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Desa</label>
                <select name="village" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($villages as $v)
                        <option value="{{ $v }}" @selected(($filters['village'] ?? '') === $v)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Posyandu</label>
                <select name="posyandu" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($posyandus as $p)
                        <option value="{{ $p }}" @selected(($filters['posyandu'] ?? '') === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Berat Badan</label>
                <select name="weight" class="form-select">
                    <option value="">Semua</option>
                    <option value="naik" @selected(($filters['weight'] ?? '') === 'naik')>Naik</option>
                    <option value="tidak_naik" @selected(($filters['weight'] ?? '') === 'tidak_naik')>Tidak naik</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Risiko</label>
                <select name="trend" class="form-select">
                    <option value="">Semua</option>
                    <option value="memburuk" @selected(($filters['trend'] ?? '') === 'memburuk')>Memburuk</option>
                    <option value="membaik" @selected(($filters['trend'] ?? '') === 'membaik')>Membaik</option>
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Balita</th>
                    <th>Pemeriksaan</th>
                    <th>BB</th>
                    <th>TB</th>
                    <th>LiLA</th>
                    <th>Status BB</th>
                    <th>Risiko (sebelumnya &rarr; terakhir)</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $t = $row['toddler'];
                        $m = $row['latest'];
                        $prev = $row['previous'];
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('balita.show', $t) }}" class="text-decoration-none fw-semibold">{{ $t->name }}</a>
                            <div class="text-muted small text-nowrap"><span class="font-monospace">{{ $t->code }}</span> &middot; {{ $t->gender }} &middot; {{ $t->posyandu ?? '-' }}</div>
                        </td>
                        <td class="small text-nowrap">
                            {{ $m->measurement_date->format('d/m/Y') }} &middot; {{ $m->age_months }} bln
                            <div class="text-muted">{{ $prev ? 'vs '.$prev->measurement_date->format('d/m/Y') : 'baru 1x diperiksa' }}</div>
                        </td>
                        <td class="small text-nowrap">{{ $m->weight_kg }} kg{!! $delta($row['weight_delta'], 'kg') !!}</td>
                        <td class="small text-nowrap">{{ $m->height_cm }} cm{!! $delta($row['height_delta'], 'cm') !!}</td>
                        <td class="small text-nowrap">{{ $m->muac_cm ? $m->muac_cm.' cm' : '-' }}{!! $delta($row['muac_delta'], 'cm') !!}</td>
                        <td class="text-nowrap">
                            @if ($row['weight_status'])
                                <span class="badge {{ $weightBadges[$row['weight_status']][1] }}">{{ $weightBadges[$row['weight_status']][0] }}</span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="small">
                            @if ($row['prediction'])
                                @if ($row['previous_prediction'])
                                    <span class="badge text-bg-{{ $row['previous_prediction']->statusClass() }} opacity-75">{{ $row['previous_prediction']->statusLabel() }}</span>
                                    <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                @endif
                                <span class="badge text-bg-{{ $row['prediction']->statusClass() }}">{{ $row['prediction']->statusLabel() }}</span>
                                @if ($row['trend'] && $row['trend'] !== 'tetap')
                                    <span class="badge {{ $trendBadges[$row['trend']][1] }} ms-1"><i class="bi {{ $trendBadges[$row['trend']][2] }}"></i> {{ $trendBadges[$row['trend']][0] }}</span>
                                @endif
                            @else
                                <span class="text-muted">Belum diprediksi</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('balita.show', $t) }}" class="btn btn-sm btn-outline-secondary" title="Detail & grafik pertumbuhan"><i class="bi bi-graph-up"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="sip-empty"><i class="bi bi-graph-up"></i>Belum ada data pemeriksaan untuk filter ini.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($rows->hasPages())
        <div class="card-body border-top">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
