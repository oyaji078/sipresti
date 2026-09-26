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

        // Berkas CSV sering terdeteksi sebagai text/plain sehingga aturan
        // `mimes:csv` menolaknya. Karena itu ekstensi dan tipe MIME diperiksa
        // terpisah: ekstensi dibatasi ketat, tipe MIME diberi daftar yang
        // memang muncul di praktik untuk CSV/XLSX.
        return [
            'file' => [
                'required',
                'file',
                'max:'.$cfg['max_file_size_kb'],
                'extensions:'.implode(',', $cfg['allowed_extensions']),
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,'
                    .'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
                    .'application/zip,application/octet-stream',
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
