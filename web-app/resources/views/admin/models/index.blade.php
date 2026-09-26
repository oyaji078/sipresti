@extends('layouts.app')

@section('title', 'Model XGBoost')
@section('page-title', 'Model XGBoost')
@section('page-subtitle', 'Katalog versi model dan status layanan prediksi.')

@section('page-actions')
    <div class="btn-group">
        <a href="{{ route('admin.models.metrics') }}" class="btn btn-outline-secondary"><i class="bi bi-speedometer2 me-1"></i>Metrics</a>
        <form method="POST" action="{{ route('admin.models.sync') }}" data-loading="Menyinkronkan...">
            @csrf
            <button type="submit" class="btn btn-primary" @disabled(! $configured)>
                <i class="bi bi-arrow-repeat me-1"></i>Sinkronisasi
            </button>
        </form>
    </div>
@endsection

@section('content')

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-hdd-network me-1"></i>Status Layanan Prediksi</div>
    <div class="card-body">
        @if (! $configured)
            <div class="alert alert-danger mb-0">
                <i class="bi bi-x-octagon me-1"></i>
                <strong>ML API belum dikonfigurasi.</strong>
                Isi <span class="font-monospace">ML_API_URL</span> dan <span class="font-monospace">ML_API_KEY</span>
                pada berkas <span class="font-monospace">.env</span> aplikasi.
            </div>
        @else
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="sip-stat">
                        <div class="sip-stat__label">Koneksi</div>
                        <div class="mt-2">
                            @if ($health['reachable'])
                                <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Terhubung</span>
                            @else
                                <span class="badge text-bg-danger"><i class="bi bi-x-circle me-1"></i>Tidak Terhubung</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="sip-stat">
                        <div class="sip-stat__label">Model Dimuat</div>
                        <div class="mt-2">
                            @if ($health['model_loaded'])
                                <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Ya</span>
                            @else
                                <span class="badge text-bg-warning"><i class="bi bi-exclamation-triangle me-1"></i>Belum</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="sip-stat">
                        <div class="sip-stat__label">Versi Aktif</div>
                        <div class="mt-2 fw-bold font-monospace">{{ $health['model_version'] ?? '-' }}</div>
                    </div>
                </div>
            </div>
            <p class="text-muted small mb-0 mt-3">{{ $health['message'] }}</p>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">Riwayat Versi Model</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Versi</th><th>Algoritma</th><th>Fitur</th><th>Dilatih</th>
                    <th class="text-end">Accuracy</th><th class="text-end">Precision</th>
                    <th class="text-end">Recall</th><th class="text-end">F1</th>
                    <th class="text-end">Dipakai</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($models as $m)
                    <tr>
                        <td class="font-monospace fw-semibold">{{ $m->version }}</td>
                        <td class="small">{{ $m->algorithm }}</td>
                        <td class="small">
                            @if ($m->features_json)
                                {{ implode(', ', (array) $m->features_json) }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="small">{{ $m->trained_at?->format('d/m/Y') ?? '-' }}</td>
                        <td class="text-end small">{{ $m->metricLabel($m->accuracy) }}</td>
                        <td class="text-end small">{{ $m->metricLabel($m->precision_score) }}</td>
                        <td class="text-end small">{{ $m->metricLabel($m->recall) }}</td>
                        <td class="text-end small">{{ $m->metricLabel($m->f1_score) }}</td>
                        <td class="text-end small">{{ number_format($m->predictions_count, 0, ',', '.') }}</td>
                        <td>
                            <span class="badge {{ $m->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $m->is_active ? 'ACTIVE' : 'INACTIVE' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">
                            <div class="sip-empty">
                                <i class="bi bi-diagram-3"></i>
                                <div class="fw-semibold mb-1">Belum ada versi model tercatat.</div>
                                <div class="small">Tekan <strong>Sinkronisasi</strong> setelah artifact model tersedia di ML API.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($models->hasPages())
        <div class="card-body border-top">{{ $models->links() }}</div>
    @endif
</div>

<div class="sip-disclaimer mt-3">
    <i class="bi bi-info-circle me-1"></i>
    Metrik hanya terisi bila ML API mengirimkan hasil training yang sebenarnya. Nilai <strong>-</strong> berarti
    metrik belum tersedia, bukan nol. Artifact model diperbarui melalui proses deployment ML API, bukan melalui unggah dari halaman ini.
</div>
@endsection
