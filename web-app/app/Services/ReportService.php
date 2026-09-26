<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Measurement;
use App\Models\Prediction;
use App\Models\Toddler;
use Illuminate\Database\Eloquent\Builder;

/**
 * Penyedia data laporan. Filter yang sama dipakai untuk tampilan layar,
 * halaman cetak, dan ekspor CSV — supaya hasil ekspor selalu konsisten
 * dengan apa yang dilihat pengguna.
 */
class ReportService
{
    public const TYPE_TODDLER = 'balita';
    public const TYPE_MEASUREMENT = 'pemeriksaan';
    public const TYPE_PREDICTION = 'prediksi';

    /** @return array<string, string> */
    public static function types(): array
    {
        return [
            self::TYPE_TODDLER => 'Laporan Data Balita',
            self::TYPE_MEASUREMENT => 'Laporan Pemeriksaan',
            self::TYPE_PREDICTION => 'Laporan Hasil Prediksi',
        ];
    }

    public function query(string $type, array $filters): Builder
    {
        return match ($type) {
            self::TYPE_MEASUREMENT => $this->measurementQuery($filters),
            self::TYPE_PREDICTION => $this->predictionQuery($filters),
            default => $this->toddlerQuery($filters),
        };
    }

    private function toddlerQuery(array $filters): Builder
    {
        return Toddler::query()
            ->with('latestMeasurement')
            ->when($filters['gender'] ?? null, fn ($q, $v) => $q->where('gender', $v))
            ->when($filters['village'] ?? null, fn ($q, $v) => $q->where('village', $v))
            ->when($filters['posyandu'] ?? null, fn ($q, $v) => $q->where('posyandu', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderBy('name');
    }

    private function measurementQuery(array $filters): Builder
    {
        return Measurement::query()
            ->with('toddler:id,code,name,gender,village,posyandu')
            ->when($filters['gender'] ?? null, function ($q, $v): void {
                $q->whereHas('toddler', fn ($sub) => $sub->where('gender', $v));
            })
            ->when($filters['village'] ?? null, function ($q, $v): void {
                $q->whereHas('toddler', fn ($sub) => $sub->where('village', $v));
            })
            ->when($filters['posyandu'] ?? null, function ($q, $v): void {
                $q->whereHas('toddler', fn ($sub) => $sub->where('posyandu', $v));
            })
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('measurement_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('measurement_date', '<=', $v))
            ->orderByDesc('measurement_date');
    }

    private function predictionQuery(array $filters): Builder
    {
        return Prediction::query()
            ->with([
                'toddler:id,code,name,gender,village,posyandu',
                'measurement:id,measurement_date,age_months,weight_kg,height_cm',
                'modelVersion:id,version',
                'predictor:id,name',
            ])
            ->when($filters['gender'] ?? null, function ($q, $v): void {
                $q->whereHas('toddler', fn ($sub) => $sub->where('gender', $v));
            })
            ->when($filters['village'] ?? null, function ($q, $v): void {
                $q->whereHas('toddler', fn ($sub) => $sub->where('village', $v));
            })
            ->when($filters['posyandu'] ?? null, function ($q, $v): void {
                $q->whereHas('toddler', fn ($sub) => $sub->where('posyandu', $v));
            })
            ->when(($filters['result'] ?? null) === 'risiko', fn ($q) => $q->atRisk())
            ->when(($filters['result'] ?? null) === 'tidak', fn ($q) => $q->notAtRisk())
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('predicted_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('predicted_at', '<=', $v))
            ->orderByDesc('predicted_at');
    }

    /** @return array{0: array<int,string>, 1: \Closure} header dan pemeta baris untuk CSV */
    public function csvDefinition(string $type): array
    {
        return match ($type) {
            self::TYPE_MEASUREMENT => [
                ['Tanggal', 'Kode', 'Nama', 'JK', 'Usia (bulan)', 'BB (kg)', 'TB (cm)', 'LiLA (cm)', 'Desa', 'Posyandu', 'Sumber'],
                fn (Measurement $m) => [
                    $m->measurement_date->format('d/m/Y'),
                    $m->toddler->code,
                    $m->toddler->name,
                    $m->toddler->gender,
                    $m->age_months,
                    $m->weight_kg,
                    $m->height_cm,
                    $m->muac_cm,
                    $m->toddler->village,
                    $m->toddler->posyandu,
                    $m->source->value,
                ],
            ],
            self::TYPE_PREDICTION => [
                ['Tanggal Prediksi', 'Kode', 'Nama', 'JK', 'Usia (bulan)', 'BB (kg)', 'TB (cm)', 'Hasil', 'Probabilitas', 'Model', 'Petugas'],
                fn (Prediction $p) => [
                    $p->predicted_at->format('d/m/Y H:i'),
                    $p->toddler->code,
                    $p->toddler->name,
                    $p->toddler->gender,
                    $p->measurement?->age_months,
                    $p->measurement?->weight_kg,
                    $p->measurement?->height_cm,
                    $p->prediction_label,
                    number_format($p->probability, 6, '.', ''),
                    $p->modelVersion?->version ?? '-',
                    $p->predictor?->name,
                ],
            ],
            default => [
                ['Kode', 'Nama', 'NIK', 'JK', 'Tanggal Lahir', 'Nama Ibu', 'Desa', 'Posyandu', 'Pemeriksaan Terakhir'],
                fn (Toddler $t) => [
                    $t->code,
                    $t->name,
                    $t->nik,
                    $t->gender,
                    $t->birth_date->format('d/m/Y'),
                    $t->mother_name,
                    $t->village,
                    $t->posyandu,
                    $t->latestMeasurement?->measurement_date?->format('d/m/Y') ?? '-',
                ],
            ],
        };
    }
}
