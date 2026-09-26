<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportEppbgmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPetugasGizi() ?? false;
    }

    public function rules(): array
    {
        $cfg = config('sipresti.import');

        // Deteksi tipe MIME dari isi berkas tidak konsisten antar OS/versi
        // Excel untuk CSV maupun XLS (mis. terdeteksi text/plain,
        // application/x-ole-storage, dll di luar daftar yang diduga), dan
        // menolak berkas yang sah. Ekstensi sudah dibatasi ketat di sini, dan
        // isi berkas yang benar-benar tidak terbaca tetap ditolak dengan
        // aman oleh ImportService::stage() saat pembacaan baris.
        return [
            'file' => [
                'required',
                'file',
                'max:'.$cfg['max_file_size_kb'],
                'extensions:'.implode(',', $cfg['allowed_extensions']),
            ],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'berkas import'];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Pilih berkas yang akan diimpor.',
            'file.extensions' => 'Format berkas harus CSV, XLSX, atau XLS.',
            'file.mimetypes' => 'Isi berkas tidak dikenali sebagai CSV maupun Excel.',
            'file.max' => 'Ukuran berkas melebihi batas yang diizinkan.',
        ];
    }
}
