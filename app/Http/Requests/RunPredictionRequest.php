<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunPredictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPetugasGizi() ?? false;
    }

    public function rules(): array
    {
        return [
            'measurement_id' => ['required', 'integer', Rule::exists('measurements', 'id')],
        ];
    }

    public function attributes(): array
    {
        return ['measurement_id' => 'data pemeriksaan'];
    }

    public function messages(): array
    {
        return [
            'measurement_id.required' => 'Pilih data pemeriksaan yang akan dianalisis.',
            'measurement_id.exists' => 'Data pemeriksaan tidak ditemukan.',
        ];
    }
}
