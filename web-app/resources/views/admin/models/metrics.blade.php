@extends('layouts.app')

@section('title', 'Metrics Model')
@section('page-title', 'Metrics Model Aktif')
@section('page-subtitle', 'Hasil evaluasi model yang sedang digunakan sistem.')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.models.index') }}">Model XGBoost</a></li>
    <li class="breadcrumb-item active">Metrics</li>
@endsection

@section('content')
@if (! $active)
    <div class="card">
        <div class="card-body">
            <div class="sip-empty">
                <i class="bi bi-speedometer2"></i>
                <div class="fw-semibold mb-1">Belum ada model aktif.</div>
                <div class="small mb-3">Lakukan sinkronisasi setelah model tersedia di ML API.</div>
                <a href="{{ route('admin.models.index') }}" class="btn btn-sm btn-primary">Ke Halaman Model</a>
            </div>
        </div>
    </div>
@else
    <div class="row g-3 mb-3">
        @php
            $metrics = [
                ['Accuracy', $active->accuracy, 'bi-bullseye'],
                ['Precision', $active->precision_score, 'bi-crosshair'],
                ['Recall', $active->recall, 'bi-search'],
                ['F1-Score', $active->f1_score, 'bi-award'],
            ];
        @endphp
        @foreach ($metrics as [$label, $value, $icon])
            <div class="col-6 col-lg-3">
                <div class="sip-stat text-center">
                    <div class="sip-stat__icon sip-soft-primary mx-auto mb-2"><i class="bi {{ $icon }}"></i></div>
                    <div class="sip-stat__value">{{ $active->metricLabel($value) }}</div>
                    <div class="sip-stat__label">{{ $label }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @unless ($active->hasMetrics())
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Metrik belum tersedia dari ML API sehingga ditampilkan sebagai <strong>-</strong>.
            Nilai akan terisi setelah model dilatih dengan dataset asli dan hasilnya disertakan pada
            <span class="font-monospace">metadata.json</span>.
        </div>
    @endunless

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-diagram-3 me-1"></i>Informasi Model</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted fw-normal">Versi</dt>
                        <dd class="col-7 font-monospace fw-semibold">{{ $active->version }}</dd>

                        <dt class="col-5 text-muted fw-normal">Algoritma</dt>
                        <dd class="col-7">{{ $active->algorithm }}</dd>

                        <dt class="col-5 text-muted fw-normal">Fitur</dt>
                        <dd class="col-7">{{ $active->features_json ? implode(', ', (array) $active->features_json) : '-' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Tanggal Training</dt>
                        <dd class="col-7">{{ $active->trained_at?->format('d/m/Y H:i') ?? '-' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Ukuran Dataset</dt>
                        <dd class="col-7">{{ $active->dataset_size ? number_format($active->dataset_size, 0, ',', '.') : '-' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Data Latih</dt>
                        <dd class="col-7">{{ $active->training_size ? number_format($active->training_size, 0, ',', '.') : '-' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Data Uji</dt>
                        <dd class="col-7">{{ $active->testing_size ? number_format($active->testing_size, 0, ',', '.') : '-' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Checksum</dt>
                        <dd class="col-7 font-monospace" style="font-size:.7rem;word-break:break-all">{{ $active->checksum ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-activity me-1"></i>Pemakaian</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-7 text-muted fw-normal">Prediksi memakai model ini</dt>
                        <dd class="col-5 fw-semibold">{{ number_format($usage, 0, ',', '.') }}</dd>

                        <dt class="col-7 text-muted fw-normal">Total prediksi di sistem</dt>
                        <dd class="col-5 fw-semibold">{{ number_format($totalPredictions, 0, ',', '.') }}</dd>

                        <dt class="col-7 text-muted fw-normal">Status layanan</dt>
                        <dd class="col-5">
                            <span class="badge {{ $health['model_loaded'] ? 'text-bg-success' : 'text-bg-warning' }}">
                                {{ $health['model_loaded'] ? 'Siap' : 'Belum siap' }}
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>

            @if ($active->parameters_json)
                <div class="card">
                    <div class="card-header"><i class="bi bi-sliders me-1"></i>Hyperparameter</div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                                @foreach ((array) $active->parameters_json as $key => $value)
                                    <tr>
                                        <td class="text-muted small">{{ $key }}</td>
                                        <td class="text-end small font-monospace">{{ is_scalar($value) ? $value : json_encode($value) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
@endsection
