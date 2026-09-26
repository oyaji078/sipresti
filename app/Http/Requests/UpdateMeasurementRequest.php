<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Measurement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPetugasGizi() ?? false;
    }

    public function rules(): array
    {
        $cfg = config('sipresti.measurement');
        $measurement = $this->route('measurement');

        return [
            'measurement_date' => [
                'required', 'date', 'before_or_equal:today',
                Rule::unique('measurements', 'measurement_date')
                    ->where(fn ($q) => $q->where('toddler_id', $measurement->toddler_id))
                    ->ignore($measurement->id),
            ],
            'weight_kg' => ['required', 'numeric', 'between:'.$cfg['weight_kg_min'].','.$cfg['weight_kg_max']],
            'height_cm' => ['required', 'numeric', 'between:'.$cfg['height_cm_min'].','.$cfg['height_cm_max']],
        ];
    }

    public function attributes(): array
    {
        return [
            'measurement_date' => 'tanggal pemeriksaan',
            'weight_kg' => 'berat badan',
            'height_cm' => 'tinggi/panjang badan',
        ];
    }

    public function messages(): array
    {
        return [
            'measurement_date.unique' => 'Balita ini sudah memiliki data pemeriksaan pada tanggal tersebut.',
            'weight_kg.between' => 'Berat badan di luar batas wajar. Periksa kembali hasil penimbangan.',
            'height_cm.between' => 'Tinggi/panjang badan di luar batas wajar. Periksa kembali hasil pengukuran.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('measurement_date')) {
                return;
            }

            /** @var Measurement $measurement */
            $measurement = $this->route('measurement');
            $toddler = $measurement->toddler;
            $date = $this->date('measurement_date');

            if ($date && $date->lt($toddler->birth_date)) {
                $validator->errors()->add(
                    'measurement_date',
                    'Tanggal pemeriksaan tidak boleh sebelum tanggal lahir balita ('.$toddler->birth_date->format('d/m/Y').').'
                );
                return;
            }

            $maxAge = (int) config('sipresti.measurement.age_months_max');
            $age = Measurement::calculateAgeMonths($toddler->birth_date, $date);

            if ($age > $maxAge) {
                $validator->errors()->add(
                    'measurement_date',
                    "Usia balita pada tanggal tersebut {$age} bulan, melebihi batas {$maxAge} bulan."
                );
            }
        });
    }
}
