<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\MlPredictionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    /**
     * Pengaturan yang boleh diubah lewat antarmuka.
     * Kredensial (URL/API key ML, kredensial database) sengaja TIDAK ada di
     * sini — hanya lewat environment variable.
     */
    private const EDITABLE = [
        'app.instansi' => ['label' => 'Nama Instansi', 'type' => 'string'],
        'app.alamat' => ['label' => 'Alamat Instansi', 'type' => 'string'],
        'app.kepala' => ['label' => 'Nama Kepala Puskesmas', 'type' => 'string'],
        'app.nip_kepala' => ['label' => 'NIP Kepala Puskesmas', 'type' => 'string'],
        'report.footer' => ['label' => 'Catatan Kaki Laporan', 'type' => 'string'],
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly MlPredictionService $ml,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'fields' => self::EDITABLE,
            'values' => Setting::allValues(),
            'health' => $this->ml->health(),
            'mlUrlConfigured' => filled(config('sipresti.ml.url')),
            'mlKeyConfigured' => filled(config('sipresti.ml.key')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Titik pada nama kunci harus di-escape, kalau tidak Laravel
        // menganggapnya sebagai akses larik bersarang dan isian tidak terbaca.
        $rules = [];
        foreach (array_keys(self::EDITABLE) as $key) {
            $rules['settings.'.str_replace('.', '\.', $key)] = ['nullable', 'string', 'max:255'];
        }

        $request->validate($rules);
        $settings = $request->input('settings', []);

        foreach (self::EDITABLE as $key => $meta) {
            Setting::put($key, $settings[$key] ?? null, $request->user()->id);
        }

        $this->audit->log(
            AuditLog::ACTION_SETTING_UPDATE,
            'setting',
            null,
            'Memperbarui pengaturan sistem',
        );

        return back()->with('toast', ['message' => 'Pengaturan berhasil disimpan.', 'type' => 'success']);
    }
}
