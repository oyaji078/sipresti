<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ModelVersionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MeasurementController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ToddlerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute Tamu
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

/*
|--------------------------------------------------------------------------
| Rute Terautentikasi
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function (): void {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ---------------------------------------------------------- Data Balita
    // Admin hanya boleh melihat; tulis dibatasi untuk PETUGAS_GIZI.
    Route::get('/balita', [ToddlerController::class, 'index'])->name('balita.index');
    Route::get('/balita/{toddler}', [ToddlerController::class, 'show'])
        ->whereNumber('toddler')->name('balita.show');

    Route::middleware('role:PETUGAS_GIZI')->group(function (): void {
        Route::get('/balita-tambah', [ToddlerController::class, 'create'])->name('balita.create');
        Route::post('/balita', [ToddlerController::class, 'store'])->name('balita.store');
        Route::get('/balita/{toddler}/edit', [ToddlerController::class, 'edit'])->name('balita.edit');
        Route::put('/balita/{toddler}', [ToddlerController::class, 'update'])->name('balita.update');
        Route::delete('/balita/{toddler}', [ToddlerController::class, 'destroy'])->name('balita.destroy');
    });

    // ----------------------------------------------------------- Pemeriksaan
    Route::get('/pemeriksaan', [MeasurementController::class, 'index'])->name('pemeriksaan.index');
    Route::get('/pemeriksaan/{measurement}', [MeasurementController::class, 'show'])
        ->whereNumber('measurement')->name('pemeriksaan.show');

    Route::middleware('role:PETUGAS_GIZI')->group(function (): void {
        Route::get('/pemeriksaan-tambah', [MeasurementController::class, 'create'])->name('pemeriksaan.create');
        Route::post('/pemeriksaan', [MeasurementController::class, 'store'])->name('pemeriksaan.store');
        Route::get('/pemeriksaan/{measurement}/edit', [MeasurementController::class, 'edit'])->name('pemeriksaan.edit');
        Route::put('/pemeriksaan/{measurement}', [MeasurementController::class, 'update'])->name('pemeriksaan.update');
        Route::delete('/pemeriksaan/{measurement}', [MeasurementController::class, 'destroy'])->name('pemeriksaan.destroy');
    });

    // -------------------------------------------------------------- Prediksi
    Route::get('/prediksi', [PredictionController::class, 'index'])->name('prediksi.index');
    Route::get('/prediksi/{prediction}', [PredictionController::class, 'show'])
        ->whereNumber('prediction')->name('prediksi.show');

    Route::middleware('role:PETUGAS_GIZI')->group(function (): void {
        Route::get('/prediksi-baru', [PredictionController::class, 'create'])->name('prediksi.create');
        Route::post('/prediksi', [PredictionController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('prediksi.store');
        Route::get('/prediksi-massal', [PredictionController::class, 'batch'])->name('prediksi.batch');
        Route::post('/prediksi-massal', [PredictionController::class, 'batchRun'])
            ->middleware('throttle:60,1')
            ->name('prediksi.batch.run');
    });

    // ------------------------------------------------- Import, Monitoring, Laporan
    Route::middleware('role:PETUGAS_GIZI')->group(function (): void {
        Route::get('/import', [ImportController::class, 'index'])->name('import.index');
        Route::post('/import/upload', [ImportController::class, 'upload'])->name('import.upload');
        Route::post('/import/mapping', [ImportController::class, 'mapping'])->name('import.mapping');
        Route::post('/import/perbaiki', [ImportController::class, 'correct'])->name('import.correct');
        Route::post('/import/commit', [ImportController::class, 'commit'])->name('import.commit');
        Route::get('/import/{importHistory}', [ImportController::class, 'show'])
            ->whereNumber('importHistory')->name('import.show');
        Route::get('/import-template', [ImportController::class, 'template'])->name('import.template');

        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');

        Route::get('/laporan', [ReportController::class, 'index'])->name('laporan.index');
        Route::get('/laporan/cetak', [ReportController::class, 'print'])->name('laporan.print');
        Route::get('/laporan/ekspor', [ReportController::class, 'export'])->name('laporan.export');
    });

    // ---------------------------------------------------------------- Profil
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profil.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profil.update');
    Route::put('/profil/kata-sandi', [ProfileController::class, 'updatePassword'])->name('profil.password');

    // ------------------------------------------------------- Endpoint internal
    Route::prefix('api/internal')->name('internal.')->group(function (): void {
        Route::get('/balita/{toddler}/growth', [ToddlerController::class, 'growthData'])
            ->whereNumber('toddler')->name('growth');
        Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('stats');
    });

    /*
    |----------------------------------------------------------------------
    | Area Admin
    |----------------------------------------------------------------------
    */
    Route::middleware('role:ADMIN')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
        Route::patch('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

        Route::get('/models', [ModelVersionController::class, 'index'])->name('models.index');
        Route::post('/models/sync', [ModelVersionController::class, 'sync'])->name('models.sync');
        Route::get('/model-metrics', [ModelVersionController::class, 'metrics'])->name('models.metrics');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    });
});
