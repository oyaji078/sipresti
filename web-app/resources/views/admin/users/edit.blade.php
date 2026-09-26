@extends('layouts.app')

@section('title', 'Ubah Pengguna')
@section('page-title', 'Ubah Pengguna')
@section('page-subtitle', $user->name . ' (' . $user->username . ')')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Pengguna</a></li>
    <li class="breadcrumb-item active">Ubah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-8">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            <div class="card">
                <div class="card-header"><i class="bi bi-person-gear me-1"></i>Data Pengguna</div>
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
                        <div class="col-md-6">
                            <label for="role" class="form-label fw-semibold sip-required">Peran</label>
                            <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                                @foreach (\App\Enums\UserRole::options() as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role', $user->role->value) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label fw-semibold sip-required">Status</label>
                            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                @foreach (\App\Enums\UserStatus::options() as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $user->status->value) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="sip-disclaimer mt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Kata sandi tidak dapat diubah dari halaman ini. Gunakan tombol <strong>Reset Kata Sandi</strong>
                        pada daftar pengguna; kata sandi baru akan dibuat acak dan ditampilkan satu kali.
                    </div>
                </div>
                <div class="card-body border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
