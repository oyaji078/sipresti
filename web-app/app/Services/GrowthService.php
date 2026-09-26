<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Measurement;
use App\Models\Toddler;
use Illuminate\Support\Collection;

/**
 * Perkembangan balita: pemeriksaan terakhir dibandingkan dengan pemeriksaan
 * sebelumnya (mis. ekspor E-PPBGM bulan lalu vs bulan ini).
 */
class GrowthService
{
    public const WEIGHT_UP = 'naik';
    public const WEIGHT_SAME = 'tetap';
    public const WEIGHT_DOWN = 'turun';

    public const TREND_WORSE = 'memburuk';
    public const TREND_BETTER = 'membaik';
    public const TREND_SAME = 'tetap';

    /**
     * @param  array<string, mixed>  $filters  q, village, posyandu, weight (naik|tidak_naik), trend (memburuk|membaik), risk (risiko)
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(array $filters): Collection
    {
        $toddlers = Toddler::query()
            ->search($filters['q'] ?? null)
            ->when($filters['village'] ?? null, fn ($q, $v) => $q->where('village', $v))
            ->when($filters['posyandu'] ?? null, fn ($q, $v) => $q->where('posyandu', $v))
            ->whereHas('measurements')
            ->with(['measurements' => fn ($q) => $q
                ->orderByDesc('measurement_date')
                ->limit(2)
                ->with(['predictions' => fn ($p) => $p->orderByDesc('predicted_at')]),
            ])
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'gender', 'village', 'posyandu']);

        return $toddlers
            ->map(fn (Toddler $toddler): array => $this->row($toddler))
            ->filter(fn (array $row): bool => $this->matches($row, $filters))
            ->values();
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    public function summary(Collection $rows): array
    {
        return [
            'toddlers' => $rows->count(),
            'compared' => $rows->whereNotNull('previous')->count(),
            'weight_up' => $rows->where('weight_status', self::WEIGHT_UP)->count(),
            'weight_not_up' => $rows->whereIn('weight_status', [self::WEIGHT_SAME, self::WEIGHT_DOWN])->count(),
            'worse' => $rows->where('trend', self::TREND_WORSE)->count(),
            'at_risk' => $rows->where('at_risk', true)->count(),
        ];
    }

    private function row(Toddler $toddler): array
    {
        /** @var Measurement $latest */
        $latest = $toddler->measurements[0];
        /** @var Measurement|null $previous */
        $previous = $toddler->measurements[1] ?? null;

        $weightDelta = $previous ? round((float) $latest->weight_kg - (float) $previous->weight_kg, 2) : null;
        $heightDelta = $previous ? round((float) $latest->height_cm - (float) $previous->height_cm, 1) : null;
        $muacDelta = $previous && $latest->muac_cm !== null && $previous->muac_cm !== null
            ? round((float) $latest->muac_cm - (float) $previous->muac_cm, 1)
            : null;

        $prediction = $latest->predictions->first();
        $previousPrediction = $previous?->predictions->first();

        $trend = null;
        if ($prediction && $previousPrediction) {
            $trend = match (true) {
                ! $previousPrediction->isAtRisk() && $prediction->isAtRisk() => self::TREND_WORSE,
                $previousPrediction->isAtRisk() && ! $prediction->isAtRisk() => self::TREND_BETTER,
                default => self::TREND_SAME,
            };
        }

        return [
            'toddler' => $toddler,
            'latest' => $latest,
            'previous' => $previous,
            'weight_delta' => $weightDelta,
            'height_delta' => $heightDelta,
            'muac_delta' => $muacDelta,
            'weight_status' => match (true) {
                $weightDelta === null => null,
                $weightDelta > 0 => self::WEIGHT_UP,
                $weightDelta < 0 => self::WEIGHT_DOWN,
                default => self::WEIGHT_SAME,
            },
            'prediction' => $prediction,
            'previous_prediction' => $previousPrediction,
            'at_risk' => $prediction?->isAtRisk() ?? false,
            'trend' => $trend,
        ];
    }

    private function matches(array $row, array $filters): bool
    {
        return match ($filters['weight'] ?? null) {
            'naik' => $row['weight_status'] === self::WEIGHT_UP,
            'tidak_naik' => in_array($row['weight_status'], [self::WEIGHT_SAME, self::WEIGHT_DOWN], true),
            default => true,
        } && match ($filters['trend'] ?? null) {
            self::TREND_WORSE, self::TREND_BETTER => $row['trend'] === $filters['trend'],
            default => true,
        } && (($filters['risk'] ?? null) !== 'risiko' || $row['at_risk']);
    }
}
