<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureUserIsActive::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Unggahan besar (import E-PPBGM) dapat melampaui post_max_size di
        // server sehingga PHP mengosongkan seluruh $_POST/$_FILES, termasuk
        // token CSRF. Tanpa penanganan ini pengguna hanya melihat halaman
        // generik (419/413) yang membingungkan; di sini diarahkan kembali
        // dengan pesan yang jelas, tanpa kehilangan sesi login.
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return back()->with('error', 'Berkas terlalu besar untuk diunggah ke server. Hubungi administrator untuk menaikkan batas post_max_size.');
        });

        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return back()->with('error', 'Sesi kedaluwarsa atau berkas terlalu besar. Silakan coba lagi.');
        });
    })->create();
