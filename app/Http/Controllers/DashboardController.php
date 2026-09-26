<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\MlPredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly MlPredictionService $ml,
    ) {}

    public function index(): View
    {
        return view('dashboard.index', [
            'counters' => $this->dashboard->counters(),
            'distribution' => $this->dashboard->predictionDistribution(),
            'measurementTrend' => $this->dashboard->measurementTrend(),
            'predictionTrend' => $this->dashboard->predictionTrend(),
            'latestPredictions' => $this->dashboard->latestPredictions(),
            'atRisk' => $this->dashboard->atRiskToddlers(),
            'mlConfigured' => $this->ml->isConfigured(),
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'counters' => $this->dashboard->counters(),
            'distribution' => $this->dashboard->predictionDistribution(),
            'measurement_trend' => $this->dashboard->measurementTrend(),
            'prediction_trend' => $this->dashboard->predictionTrend(),
        ]);
    }
}
