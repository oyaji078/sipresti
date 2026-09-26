@extends('layouts.app')

@section('title', 'Ringkasan Import')
@section('page-title', 'Ringkasan Import')
@section('page-subtitle', $history->original_name . ' — ' . $history->created_at->format('d/m/Y H:i'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('import.index') }}">Import E-PPBGM</a></li>
    <li class="breadcrumb-item active">#{{ $history->id }}</li>
@endsection

@section('page-actions')
    <a href="{{ route('import.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    @php
        $tiles = [
            ['Total Baris', $history->total_rows, 'sip-soft-primary'],
            ['Berhasil Diimpor', $history->success_rows, 'sip-soft-success'],
            ['Tidak Valid', $history->invalid_rows, 'sip-soft-danger'],
            ['Duplikat Dilewati', $history->duplicate_rows, 'sip-soft-warning'],
        ];
    @endphp
    @foreach ($tiles as [$label, $value, $tone])
        <div class="col-6 col-md-3">
            <div class="sip-stat text-center">
                <div class="sip-stat__icon {{ $tone }} mx-auto mb-2"><i class="bi bi-database"></i></div>
                <div class="sip-stat__value">{{ number_format($value, 0, ',', '.') }}</div>
                <div class="sip-stat__label">{{ $label }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i>Informasi Batch</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Status</dt>
                    <dd class="col-7"><span class="badge {{ $history->badgeClass() }}">{{ $history->statusLabel() }}</span></dd>

                    <dt class="col-5 text-muted fw-normal">Berkas Asal</dt>
                    <dd class="col-7">{{ $history->original_name }}</dd>

                    <dt class="col-5 text-muted fw-normal">Sidik Berkas</dt>
                    <dd class="col-7 font-monospace" style="font-size:.7rem;word-break:break-all">{{ $history->file_hash }}</dd>

                    <dt class="col-5 text-muted fw-normal">Petugas</dt>
                    <dd class="col-7">{{ $history->user?->name }}</dd>

                    <dt class="col-5 text-muted fw-normal">Waktu</dt>
                    <dd class="col-7">{{ $history->created_at->format('d/m/Y H:i') }}</dd>
                </dl>

                @if ($history->column_mapping_json)
                    <hr>
                    <div class="fw-semibold small mb-2">Pemetaan Kolom</div>
                    <ul class="list-unstyled small mb-0">
                        @foreach ($history->column_mapping_json as $field => $index)
                            <li class="d-flex justify-content-between border-bottom py-1">
                                <span class="text-muted">{{ \App\Services\ImportService::FIELDS[$field]['label'] ?? $field }}</span>
                                <span class="font-monospace">kolom ke-{{ (int) $index + 1 }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-exclamation-triangle me-1"></i>Baris Bermasalah</div>
            <div class="card-body p-0">
                @if (filled($history->error_report_json))
                    <div class="table-responsive" style="max-height:420px;overflow:auto">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Baris</th><th>Nama</th><th>Masalah</th></tr></thead>
                            <tbody>
                                @foreach ($history->error_report_json as $err)
                                    <tr>
                                        <td>{{ $err['line'] ?? '-' }}</td>
                                        <td class="small">{{ $err['name'] ?? '-' }}</td>
                                        <td class="small text-danger">{{ implode('; ', $err['problems'] ?? []) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="sip-empty"><i class="bi bi-check-circle"></i>Tidak ada baris bermasalah.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
