@extends('layouts.app')

@section('title', 'Profil')
@section('page-title', 'Profil Saya')
@section('page-subtitle', 'Kelola data akun dan kata sandi Anda.')

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="sip-avatar mx-auto mb-3" style="width:72px;height:72px;font-size:1.5rem">{{ $user->initials() }}</div>
                <div class="fw-bold">{{ $user->name }}</div>
                <div class="text-muted small">{{ '@'.$user->username }}</div>
                <div class="mt-2">
                    <span class="badge {{ $user->role->badgeClass() }}">{{ $user->role->label() }}</span>
                    <span class="badge {{ $user->status->badgeClass() }}">{{ $user->status->label() }}</span>
                </div>
                <hr>
                <dl class="row mb-0 small text-start">
                    <dt class="col-5 text-muted fw-normal">Email</dt>
                    <dd class="col-7">{{ $user->email }}</dd>
                    <dt class="col-5 text-muted fw-normal">Login Terakhir</dt>
                    <dd class="col-7">{{ $user->last_login_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Terdaftar</dt>
                    <dd class="col-7">{{ $user->created_at?->format('d/m/Y') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person-gear me-1"></i>Data Akun</div>
            <form method="POST" action="{{ route('profil.update') }}">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="name" class="form-label fw-semibold sip-required">Nama Lengkap</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $user->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label fw-semibold sip-required">Username</label>
                            <input type="text" id="username" name="username" class="form-control @error('username') is-invalid @enderror"
                                   value="{{ old('username', $user->username) }}" required>
                            @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold sip-required">Email</label>
                            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email', $user->email) }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="card-body border-top">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-key me-1"></i>Ubah Kata Sandi</div>
            <form method="POST" action="{{ route('profil.password') }}">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="current_password" class="form-label fw-semibold sip-required">Kata Sandi Saat Ini</label>
                            <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                                   class="form-control @error('current_password') is-invalid @enderror" required>
                            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-semibold sip-required">Kata Sandi Baru</label>
                            <input type="password" id="password" name="password" autocomplete="new-password"
                                   class="form-control @error('password') is-invalid @enderror" required>
                            <div class="form-text">Minimal 8 karakter, mengandung huruf dan angka.</div>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label fw-semibold sip-required">Konfirmasi Kata Sandi</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password"
                                   class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="card-body border-top">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-shield-lock me-1"></i>Ubah Kata Sandi</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
