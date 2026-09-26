<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => 'username atau email',
            'password' => 'kata sandi',
        ];
    }

    /**
     * Autentikasi menerima username maupun email.
     * Akun nonaktif ditolak walau kata sandinya benar.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifier = (string) $this->input('username');
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $field => $identifier,
            'password' => (string) $this->input('password'),
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Username/email atau kata sandi tidak sesuai.',
            ]);
        }

        if (! Auth::user()->isActive()) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Akun Anda tidak aktif. Hubungi administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower((string) $this->input('username')).'|'.$this->ip()
        );
    }
}
