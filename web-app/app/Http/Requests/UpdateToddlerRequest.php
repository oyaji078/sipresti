<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateToddlerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPetugasGizi() ?? false;
    }

    public function rules(): array
    {
        $toddlerId = $this->route('toddler')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'nik' => ['nullable', 'string', 'digits:16', Rule::unique('toddlers', 'nik')->ignore($toddlerId)],
            'birth_date' => ['required', 'date', 'before_or_equal:today', 'after:'.now()->subYears(10)->toDateString()],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'mother_name' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'village' => ['nullable', 'string', 'max:100'],
            'posyandu' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama balita',
            'nik' => 'NIK',
            'birth_date' => 'tanggal lahir',
            'gender' => 'jenis kelamin',
            'mother_name' => 'nama ibu',
        ];
    }

    public function messages(): array
    {
        return [
            'nik.digits' => 'NIK harus terdiri dari 16 digit angka.',
            'nik.unique' => 'NIK ini sudah terdaftar pada balita lain.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nik' => $this->filled('nik') ? preg_replace('/\D/', '', (string) $this->input('nik')) : null,
            'name' => trim((string) $this->input('name')),
        ]);
    }
}
