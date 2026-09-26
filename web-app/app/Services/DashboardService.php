<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Measurement;
use App\Models\Prediction;
use App\Models\Toddler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agregasi dashboard. Seluruh angka dihitung dari database; tidak ada
 * nilai contoh maupun data statis di sini.
 */
class DashboardService
{
    /** @return array<string, int> */
    public function counters(): array
    {
        $atRisk = Prediction::query()->atRisk()->count();
        $total = Prediction::query()->count();

        return [
            'toddlers' => Toddler::query()->count(),
            'measurements' => Measurement::query()->count(),
            'predictions' => $total,
            'at_risk' => $atRisk,
            'not_at_risk' => max(0, $total - $atRisk),
        ];
    }

    /** Distribusi label prediksi apa adanya, mengikuti label dari model. */
    public function predictionDistribution(): array
    {
        return Prediction::query()
            ->select('prediction_label', DB::raw('COUNT(*) as total'))
            ->groupBy('prediction_label')
            ->orderByDesc('total')
            ->pluck('total', 'prediction_label')
            ->all();
    }

    /** Tren pemeriksaan 12 bulan terakhir. */
    public function measurementTrend(int $months = 12): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $rows = Measurement::query()
            ->where('measurement_date', '>=', $start->toDateString())
            ->select(
                DB::raw("DATE_FORMAT(measurement_date, '%Y-%m') as period"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('period')
            ->pluck('total', 'period')
            ->all();

        return $this->fillMonthlySeries($rows, $start, $months);
    }

    /** Tren prediksi 12 bulan terakhir. */
    public function predictionTrend(int $months = 12): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $rows = Prediction::query()
            ->where('predicted_at', '>=', $start)
            ->select(
                DB::raw("DATE_FORMAT(predicted_at, '%Y-%m') as period"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('period')
            ->pluck('total', 'period')
            ->all();

        return $this->fillMonthlySeries($rows, $start, $months);
    }

    public function latestPredictions(int $limit = 8)
    {
        return Prediction::query()
            ->with(['toddler:id,code,name,gender', 'predictor:id,name'])
            ->latest('predicted_at')
            ->limit($limit)
            ->get();
    }

    public function atRiskToddlers(int $limit = 8)
    {
        return Prediction::query()
            ->with(['toddler:id,code,name,gender,village,posyandu', 'measurement:id,age_months,weight_kg,height_cm'])
            ->atRisk()
            ->latest('predicted_at')
            ->limit($limit)
            ->get();
    }

    /** @return array{labels: array<int,string>, values: array<int,int>} */
    private function fillMonthlySeries(array $rows, Carbon $start, int $months): array
    {
        $labels = [];
        $values = [];
        $cursor = $start->copy();

        $names = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $labels[] = $names[$cursor->month - 1].' '.$cursor->format('y');
            $values[] = (int) ($rows[$key] ?? 0);
            $cursor->addMonth();
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
