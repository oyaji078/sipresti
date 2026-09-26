<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $types[$type] }} &middot; {{ config('sipresti.short_name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sipresti.css') }}">
    <style>
        body { background:#fff; }
        .kop { border-bottom:3px double #333; padding-bottom:10px; margin-bottom:16px; }
        @media print { .no-print { display:none !important; } @page { size: A4 landscape; margin: 12mm; } }
        table { font-size: .78rem; }
    </style>
</head>
<body class="p-4">

<div class="no-print d-flex justify-content-between align-items-center mb-3">
    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    <div class="text-muted small">Gunakan "Simpan sebagai PDF" pada dialog cetak untuk menghasilkan berkas PDF.</div>
    <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
</div>

<div class="kop text-center">
    <div class="fw-bold" style="font-size:1.05rem">{{ \App\Models\Setting::get('app.instansi', config('sipresti.instansi')) }}</div>
    @if (\App\Models\Setting::get('app.alamat'))
        <div class="small">{{ \App\Models\Setting::get('app.alamat') }}</div>
    @endif
    <div class="fw-semibold mt-2" style="font-size:.95rem">{{ strtoupper($types[$type]) }}</div>
    <div class="small text-muted">
        {{ config('sipresti.long_name') }} ({{ config('sipresti.short_name') }})
    </div>
</div>

<table class="table table-sm table-bordered mb-3" style="width:auto;font-size:.75rem">
    <tbody>
        <tr>
            <th class="bg-light">Periode</th>
            <td>
                {{ $filters['date_from'] ?? 'Awal' }} s.d. {{ $filters['date_to'] ?? 'Sekarang' }}
            </td>
            <th class="bg-light">Jenis Kelamin</th>
            <td>{{ $filters['gender'] ?? 'Semua' }}</td>
        </tr>
        <tr>
            <th class="bg-light">Desa</th>
            <td>{{ $filters['village'] ?? 'Semua' }}</td>
            <th class="bg-light">Posyandu</th>
            <td>{{ $filters['posyandu'] ?? 'Semua' }}</td>
        </tr>
        <tr>
            <th class="bg-light">Jumlah Baris</th>
            <td>{{ number_format($rows->count(), 0, ',', '.') }}</td>
            <th class="bg-light">Dicetak</th>
            <td>{{ $printedAt->format('d/m/Y H:i') }} WITA</td>
        </tr>
    </tbody>
</table>

<div class="table-responsive">
    @include('laporan._table', ['type' => $type, 'rows' => $rows])
</div>

@if ($type === 'prediksi')
    <p class="small text-muted mt-3 fst-italic">
        {{ config('sipresti.disclaimer') }}
    </p>
@endif

<div class="row mt-5">
    <div class="col-8"></div>
    <div class="col-4 text-center small">
        <div>{{ \App\Models\Setting::get('report.footer', 'Dicetak dari SIPRESTI') }}</div>
        <div class="mt-1">{{ $printedAt->translatedFormat('d F Y') }}</div>
        <div class="mt-5 pt-3 fw-semibold">
            {{ \App\Models\Setting::get('app.kepala') ?: '(..............................)' }}
        </div>
        @if (\App\Models\Setting::get('app.nip_kepala'))
            <div>NIP. {{ \App\Models\Setting::get('app.nip_kepala') }}</div>
        @endif
    </div>
</div>

</body>
</html>
