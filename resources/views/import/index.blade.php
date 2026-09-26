@extends('layouts.app')

@section('title', 'Import E-PPBGM')
@section('page-title', 'Import Data E-PPBGM')
@section('page-subtitle', 'Unggah berkas ekspor E-PPBGM, petakan kolomnya, periksa hasil validasi, lalu konfirmasi.')

@section('page-actions')
    <a href="{{ route('import.template') }}" class="btn btn-outline-secondary">
        <i class="bi bi-download me-1"></i>Unduh Template
    </a>
@endsection

@section('content')

@php
    $step = $preview ? 3 : ($staged ? 2 : 1);
@endphp

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-3 justify-content-between">
            @foreach (['Unggah Berkas', 'Pemetaan Kolom', 'Validasi & Konfirmasi'] as $i => $label)
                <div class="d-flex align-items-center gap-2 flex-fill">
                    <span class="sip-stat__icon {{ $step > $i ? 'sip-soft-success' : 'sip-soft-primary' }}" style="width:34px;height:34px;font-size:.9rem">
                        @if ($step > $i + 1)
                            <i class="bi bi-check-lg"></i>
                        @else
                            {{ $i + 1 }}
                        @endif
                    </span>
                    <span class="small {{ $step === $i + 1 ? 'fw-bold' : 'text-muted' }}">{{ $label }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Langkah 1 --}}
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-1-circle me-1"></i>Unggah Berkas</div>
    <div class="card-body">
        <form method="POST" action="{{ route('import.upload') }}" enctype="multipart/form-data" data-loading="Mengunggah...">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label for="file" class="form-label fw-semibold sip-required">Berkas CSV atau XLSX</label>
                    <input type="file" id="file" name="file" accept=".csv,.xlsx,.xls"
                           class="form-control @error('file') is-invalid @enderror" required>
                    @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">
                        Maksimal {{ number_format(config('sipresti.import.max_file_size_kb') / 1024, 0) }} MB.
                        Struktur kolom bebas — pemetaan dilakukan pada langkah berikutnya.
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>Unggah &amp; Pratinjau</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Langkah 2 --}}
@if ($staged)
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-2-circle me-1"></i>Pratinjau &amp; Pemetaan Kolom</span>
            <span class="badge text-bg-light border">{{ $staged['original_name'] }} &middot; {{ number_format($staged['total'], 0, ',', '.') }} baris</span>
        </div>

        <div class="table-responsive border-bottom">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th style="width:48px">#</th>
                        @foreach ($staged['header'] as $i => $col)
                            <th>{{ $col !== '' ? $col : 'Kolom '.($i + 1) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($staged['rows'] as $r => $row)
                        <tr>
                            <td class="text-muted">{{ $r + 1 }}</td>
                            @foreach ($staged['header'] as $i => $col)
                                <td class="small">{{ $row[$i] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('import.mapping') }}" data-loading="Memvalidasi...">
                @csrf
                <div class="row g-3">
                    @foreach ($fields as $field => $meta)
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold {{ $meta['required'] ? 'sip-required' : '' }}">
                                {{ $meta['label'] }}
                            </label>
                            <select name="mapping[{{ $field }}]" class="form-select form-select-sm">
                                <option value="">— tidak dipakai —</option>
                                @foreach ($staged['header'] as $i => $col)
                                    @php
                                        $label = $col !== '' ? $col : 'Kolom '.($i + 1);
                                    @endphp
                                    <option value="{{ $i }}"
                                        @selected($preview ? (string) ($preview['mapping'][$field] ?? '') === (string) $i : ($guessed[$field] ?? null) === $i)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-square me-1"></i>Validasi Data</button>
                </div>
            </form>
        </div>
    </div>
@endif

{{-- Langkah 3 --}}
@if ($preview)
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-3-circle me-1"></i>Hasil Validasi</div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                @php
                    $s = $preview['summary'];
                    $tiles = [
                        ['Total Baris', $s['total'], 'sip-soft-primary'],
                        ['Siap Diimpor', $s['valid'], 'sip-soft-success'],
                        ['Tidak Valid', $s['invalid'], 'sip-soft-danger'],
                        ['Duplikat', $s['duplicate'], 'sip-soft-warning'],
                    ];
                @endphp
                @foreach ($tiles as [$label, $value, $tone])
                    <div class="col-6 col-md-3">
                        <div class="sip-stat text-center">
                            <div class="sip-stat__value">{{ number_format($value, 0, ',', '.') }}</div>
                            <div class="sip-stat__label">{{ $label }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if (count($preview['errors']) > 0)
                <div class="alert alert-warning">
                    <div class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Baris bermasalah (ditampilkan maksimal 100)</div>
                    <div class="table-responsive" style="max-height:260px;overflow:auto">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Baris</th><th>Nama</th><th>Masalah</th></tr></thead>
                            <tbody>
                                @foreach ($preview['errors'] as $err)
                                    <tr>
                                        <td>{{ $err['line'] }}</td>
                                        <td class="small">{{ $err['name'] }}</td>
                                        <td class="small text-danger">{{ implode('; ', $err['problems']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="small mt-2 mb-0">Baris bermasalah tidak akan diimpor. Baris lain tetap dapat diproses.</div>
                </div>
            @endif

            @if (count($preview['sample']) > 0)
                <div class="fw-semibold small mb-2">Contoh data yang akan diimpor</div>
                <div class="table-responsive border rounded mb-3">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Baris</th><th>Nama</th><th>JK</th><th>Tgl Lahir</th><th>Tgl Ukur</th><th>BB</th><th>TB</th><th>Posyandu</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($preview['sample'] as $row)
                                <tr>
                                    <td class="text-muted">{{ $row['line'] }}</td>
                                    <td class="small">{{ $row['name'] }}</td>
                                    <td class="small">{{ $row['gender'] }}</td>
                                    <td class="small">{{ $row['birth_date'] }}</td>
                                    <td class="small">{{ $row['measurement_date'] }}</td>
                                    <td class="small">{{ $row['weight_kg'] }}</td>
                                    <td class="small">{{ $row['height_cm'] }}</td>
                                    <td class="small">{{ $row['posyandu'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <form method="POST" action="{{ route('import.commit') }}"
                  data-confirm="Impor {{ $preview['summary']['valid'] }} baris data ke database?">
                @csrf
                <button type="submit" class="btn btn-primary" @disabled($preview['summary']['valid'] === 0)>
                    <i class="bi bi-database-add me-1"></i>Konfirmasi &amp; Impor {{ $preview['summary']['valid'] }} Baris
                </button>
            </form>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header"><i class="bi bi-clock-history me-1"></i>Riwayat Import</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Waktu</th><th>Berkas</th><th>Total</th><th>Berhasil</th><th>Gagal</th><th>Duplikat</th><th>Status</th><th>Petugas</th><th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($histories as $h)
                    <tr>
                        <td class="small">{{ $h->created_at->format('d/m/Y H:i') }}</td>
                        <td class="small">{{ $h->original_name }}</td>
                        <td class="small">{{ $h->total_rows }}</td>
                        <td class="small text-success fw-semibold">{{ $h->success_rows }}</td>
                        <td class="small text-danger">{{ $h->invalid_rows }}</td>
                        <td class="small text-warning">{{ $h->duplicate_rows }}</td>
                        <td><span class="badge {{ $h->badgeClass() }}">{{ $h->statusLabel() }}</span></td>
                        <td class="small">{{ $h->user?->name }}</td>
                        <td class="text-end"><a href="{{ route('import.show', $h) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="sip-empty"><i class="bi bi-inbox"></i>Belum ada riwayat import.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
