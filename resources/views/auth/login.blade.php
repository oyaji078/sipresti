<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk &middot; {{ config('sipresti.short_name') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🩺</text></svg>">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sipresti.css') }}">
</head>
<body>
<div class="sip-login">
    <div class="sip-login__card">
        <div class="text-center mb-4">
            <div class="sip-login__logo"><i class="bi bi-activity"></i></div>
            <h1 class="h4 fw-bold mb-1">{{ config('sipresti.short_name') }}</h1>
            <p class="text-muted mb-0" style="font-size:.85rem">
                {{ config('sipresti.long_name') }}<br>
                <span class="fw-semibold">{{ config('sipresti.instansi') }}</span>
            </p>
        </div>

        @if (session('status'))
            <div class="alert alert-info py-2" role="alert">
                <i class="bi bi-info-circle me-1"></i>{{ session('status') }}
            </div>
        @endif

        @error('username')
            <div class="alert alert-danger py-2" role="alert">
                <i class="bi bi-exclamation-octagon me-1"></i>{{ $message }}
            </div>
        @enderror

        <form method="POST" action="{{ route('login.store') }}" novalidate>
            @csrf

            <div class="mb-3">
                <label for="username" class="form-label fw-semibold sip-required">Username / Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                    <input type="text"
                           class="form-control @error('username') is-invalid @enderror"
                           id="username"
                           name="username"
                           value="{{ old('username') }}"
                           autocomplete="username"
                           autofocus
                           required>
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label fw-semibold sip-required">Kata Sandi</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                    <input type="password"
                           class="form-control @error('password') is-invalid @enderror"
                           id="password"
                           name="password"
                           autocomplete="current-password"
                           required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Tampilkan kata sandi">
                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
                @error('password')
                    <div class="text-danger mt-1" style="font-size:.82rem">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember">Ingat saya</label>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                <i class="bi bi-box-arrow-in-right me-1"></i>MASUK
            </button>
        </form>

        <p class="text-center text-muted mt-4 mb-0" style="font-size:.75rem">
            Sistem pendukung keputusan &mdash; bukan alat diagnosis medis.
        </p>
    </div>
</div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        var input = document.getElementById('password');
        var icon = document.getElementById('togglePasswordIcon');
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
</script>
</body>
</html>
