@extends('layouts.app')

@section('title', 'Prediksi Semua Data')
@section('page-title', 'Prediksi Semua Data')
@section('page-subtitle', 'Jalankan analisis risiko untuk seluruh data pemeriksaan yang belum pernah diprediksi.')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('prediksi.index') }}">Prediksi</a></li>
    <li class="breadcrumb-item active">Semua Data</li>
@endsection

@section('content')

@php
    $ready = $health['reachable'] && $health['model_loaded'];
@endphp

@if (! $health['reachable'])
    <div class="alert alert-danger">
        <i class="bi bi-plug me-1"></i>
        <strong>Layanan prediksi tidak dapat dihubungi.</strong> {{ $health['message'] }}
    </div>
@elseif (! $health['model_loaded'])
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>Model prediksi belum tersedia.</strong> {{ $health['message'] }}
    </div>
@else
    <div class="alert alert-success py-2">
        <i class="bi bi-check-circle me-1"></i>
        Layanan prediksi aktif &mdash; model <span class="font-monospace fw-semibold">{{ $health['model_version'] ?? '-' }}</span>.
    </div>
@endif

<div class="card">
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="sip-stat text-center">
                    <div class="sip-stat__value">{{ number_format($total, 0, ',', '.') }}</div>
                    <div class="sip-stat__label">Total Pemeriksaan</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="sip-stat text-center">
                    <div class="sip-stat__value" id="batch-pending">{{ number_format($pending, 0, ',', '.') }}</div>
                    <div class="sip-stat__label">Belum Diprediksi</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="sip-stat text-center">
                    <div class="sip-stat__value text-success" id="batch-success">0</div>
                    <div class="sip-stat__label">Berhasil (sesi ini)</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="sip-stat text-center">
                    <div class="sip-stat__value text-danger" id="batch-failed">0</div>
                    <div class="sip-stat__label">Gagal (sesi ini)</div>
                </div>
            </div>
        </div>

        <div class="progress mb-3" role="progressbar" aria-label="Kemajuan prediksi" style="height: 1.25rem">
            <div class="progress-bar progress-bar-striped" id="batch-progress" style="width: 0%">0%</div>
        </div>

        <div id="batch-status" class="small text-muted mb-3">
            @if ($pending === 0)
                Semua data pemeriksaan sudah diprediksi.
            @else
                {{ number_format($pending, 0, ',', '.') }} data pemeriksaan siap diprediksi. Jangan tutup halaman ini selama proses berjalan.
            @endif
        </div>

        <ul id="batch-errors" class="small text-danger mb-3 d-none"></ul>

        <div class="d-flex gap-2">
            <button type="button" id="batch-start" class="btn btn-primary" @disabled(! $ready || $pending === 0)>
                <i class="bi bi-play-circle me-1"></i>Mulai Prediksi Semua Data
            </button>
            <a href="{{ route('prediksi.index') }}" class="btn btn-outline-secondary d-none" id="batch-done">
                <i class="bi bi-list-check me-1"></i>Lihat Histori Prediksi
            </a>
        </div>

        <div class="sip-disclaimer mt-3">{{ config('sipresti.disclaimer') }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const startBtn = document.getElementById('batch-start');
    if (!startBtn) return;

    const el = (id) => document.getElementById(id);
    const fmt = (n) => n.toLocaleString('id-ID');
    const total = {{ $pending }};
    const errors = {};
    let processed = 0, success = 0, failed = 0, afterId = 0;

    const render = (remaining) => {
        const pct = total > 0 ? Math.round((processed / total) * 100) : 100;
        el('batch-progress').style.width = pct + '%';
        el('batch-progress').textContent = pct + '%';
        el('batch-success').textContent = fmt(success);
        el('batch-failed').textContent = fmt(failed);
        if (remaining !== undefined) el('batch-pending').textContent = fmt(remaining);

        const list = el('batch-errors');
        list.innerHTML = '';
        Object.entries(errors).forEach(([message, count]) => {
            const li = document.createElement('li');
            li.textContent = message + ' (' + fmt(count) + ' data)';
            list.appendChild(li);
        });
        list.classList.toggle('d-none', Object.keys(errors).length === 0);
    };

    const finish = (message, ok) => {
        el('batch-progress').classList.remove('progress-bar-animated');
        el('batch-progress').classList.add(ok ? 'bg-success' : 'bg-danger');
        el('batch-status').textContent = message;
        el('batch-done').classList.remove('d-none');
        startBtn.classList.add('d-none');
    };

    startBtn.addEventListener('click', async () => {
        startBtn.disabled = true;
        el('batch-progress').classList.add('progress-bar-animated');
        el('batch-status').textContent = 'Memproses… jangan tutup halaman ini.';

        while (true) {
            let body;
            try {
                const response = await fetch(@json(route('prediksi.batch.run')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ after_id: afterId }),
                });
                body = await response.json().catch(() => ({}));
                if (!response.ok) {
                    finish(body.message || 'Proses dihentikan: server mengembalikan status ' + response.status + '.', false);
                    return;
                }
            } catch (e) {
                finish('Proses dihentikan: koneksi terputus. Muat ulang halaman untuk melanjutkan.', false);
                return;
            }

            processed += body.success + body.failed;
            success += body.success;
            failed += body.failed;
            afterId = body.last_id;
            Object.entries(body.errors || {}).forEach(([m, c]) => { errors[m] = (errors[m] || 0) + c; });
            render(body.remaining + failed);

            // Seluruh batch gagal = masalah layanan, bukan data; hentikan agar tidak mengulang ratusan kegagalan.
            if (body.success === 0 && body.failed > 0) {
                finish('Proses dihentikan karena seluruh data pada putaran terakhir gagal diprediksi.', false);
                return;
            }

            if (body.done) {
                finish('Selesai: ' + fmt(success) + ' data berhasil diprediksi' + (failed ? ', ' + fmt(failed) + ' gagal.' : '.'), failed === 0);
                return;
            }
        }
    });
})();
</script>
@endpush
