<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Services\DashboardService;
use App\Services\ToddlerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function __construct(
        private readonly ToddlerService $toddlers,
        private readonly DashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'village', 'posyandu', 'result', 'date_from', 'date_to']);

        $query = Prediction::query()
            ->with([
                'toddler:id,code,name,gender,village,posyandu',
                'measurement:id,measurement_date,age_months,weight_kg,height_cm',
                'modelVersion:id,version',
            ])
            ->when($filters['q'] ?? null, function ($q, $term): void {
                $q->whereHas('toddler', fn ($sub) => $sub->search($term));
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
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('predicted_at', '<=', $v));

        $summary = [
            'total' => (clone $query)->count(),
            'at_risk' => (clone $query)->atRisk()->count(),
        ];
        $summary['not_at_risk'] = max(0, $summary['total'] - $summary['at_risk']);

        // Sebaran risiko per posyandu, dihitung langsung dari database.
        $byPosyandu = (clone $query)
            ->join('toddlers', 'toddlers.id', '=', 'predictions.toddler_id')
            ->select('toddlers.posyandu', DB::raw('COUNT(*) as total'))
            ->whereNotNull('toddlers.posyandu')
            ->groupBy('toddlers.posyandu')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'toddlers.posyandu')
            ->all();

        return view('monitoring.index', [
            'predictions' => $query->orderByDesc('predicted_at')->paginate(15)->withQueryString(),
            'summary' => $summary,
            'byPosyandu' => $byPosyandu,
            'distribution' => $this->dashboard->predictionDistribution(),
            'villages' => $this->toddlers->villages(),
            'posyandus' => $this->toddlers->posyandus(),
            'filters' => $filters,
        ]);
    }
}
