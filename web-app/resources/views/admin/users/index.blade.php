@extends('layouts.app')

@section('title', 'Pengguna')
@section('page-title', 'Manajemen Pengguna')
@section('page-subtitle', 'Kelola akun Administrator dan Petugas Gizi.')

@section('page-actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Tambah Pengguna</a>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1">Cari</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nama, username, email">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Peran</label>
                <select name="role" class="form-select">
                    <option value="">Semua</option>
                    @foreach (\App\Enums\UserRole::options() as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach (\App\Enums\UserStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">Total {{ number_format($users->total(), 0, ',', '.') }} pengguna</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr><th>Nama</th><th>Username</th><th>Email</th><th>Peran</th><th>Status</th><th>Login Terakhir</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="sip-avatar">{{ $u->initials() }}</span>
                                <span class="fw-semibold">{{ $u->name }}</span>
                            </div>
                        </td>
                        <td class="small font-monospace">{{ $u->username }}</td>
                        <td class="small">{{ $u->email }}</td>
                        <td><span class="badge {{ $u->role->badgeClass() }}">{{ $u->role->label() }}</span></td>
                        <td><span class="badge {{ $u->status->badgeClass() }}">{{ $u->status->label() }}</span></td>
                        <td class="small text-muted">{{ $u->last_login_at?->format('d/m/Y H:i') ?? 'Belum pernah' }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-outline-secondary" title="Ubah"><i class="bi bi-pencil"></i></a>

                                <form method="POST" action="{{ route('admin.users.status', $u) }}"
                                      data-confirm="{{ $u->isActive() ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $u->name }}?">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-outline-{{ $u->isActive() ? 'warning' : 'success' }}"
                                            title="{{ $u->isActive() ? 'Nonaktifkan' : 'Aktifkan' }}">
                                        <i class="bi {{ $u->isActive() ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.users.reset-password', $u) }}"
                                      data-confirm="Reset kata sandi {{ $u->name }}? Kata sandi baru akan ditampilkan satu kali.">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-outline-secondary" title="Reset Kata Sandi"><i class="bi bi-key"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="sip-empty"><i class="bi bi-people"></i>Tidak ada pengguna yang cocok.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($users->hasPages())
        <div class="card-body border-top">{{ $users->links() }}</div>
    @endif
</div>
@endsection
