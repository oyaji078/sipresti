@extends('layouts.app')

@section('title', 'Audit Log')
@section('page-title', 'Audit Log')
@section('page-subtitle', 'Jejak aktivitas pengguna pada sistem.')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Aksi</label>
                <select name="action" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Pengguna</label>
                <select name="user_id" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Dari</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Sampai</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">{{ number_format($logs->total(), 0, ',', '.') }} catatan</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Entitas</th><th>Keterangan</th><th>IP</th></tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="small text-nowrap">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td class="small">
                            {{ $log->user?->name ?? '(dihapus)' }}
                            @if ($log->user)
                                <div class="text-muted font-monospace" style="font-size:.7rem">{{ $log->user->username }}</div>
                            @endif
                        </td>
                        <td><span class="badge {{ $log->badgeClass() }}">{{ $log->action }}</span></td>
                        <td class="small">
                            {{ $log->entity ?? '-' }}
                            @if ($log->entity_id)
                                <span class="text-muted">#{{ $log->entity_id }}</span>
                            @endif
                        </td>
                        <td class="small">{{ $log->description ?? '-' }}</td>
                        <td class="small font-monospace text-muted">{{ $log->ip_address ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="sip-empty"><i class="bi bi-journal-text"></i>Belum ada catatan audit.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($logs->hasPages())
        <div class="card-body border-top">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
