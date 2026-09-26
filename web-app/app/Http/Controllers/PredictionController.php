<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\MlApiException;
use App\Http\Requests\RunPredictionRequest;
use App\Models\Measurement;
use App\Models\Prediction;
use App\Models\Toddler;
use App\Services\MlPredictionService;
use App\Services\PredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PredictionController extends Controller
{
    /** Kecil agar tiap request selesai jauh di bawah batas waktu shared hosting. */
    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly PredictionService $predictions,
        private readonly MlPredictionService $ml,
    ) {}

    public function index(Request $request): View
    {
        $result = $request->query('result');

        $predictions = Prediction::query()
            ->with(['toddler:id,code,name,gender,village,posyandu', 'measurement:id,age_months,weight_kg,height_cm', 'modelVersion:id,version', 'predictor:id,name'])
            ->when($request->query('q'), function ($query, $term): void {
                $query->whereHas('toddler', fn ($q) => $q->search($term));
            })
            ->when($result === 'risiko', fn ($q) => $q->atRisk())
            ->when($result === 'tidak', fn ($q) => $q->notAtRisk())
            ->when($request->query('date_from'), fn ($q, $v) => $q->whereDate('predicted_at', '>=', $v))
            ->when($request->query('date_to'), fn ($q, $v) => $q->whereDate('predicted_at', '<=', $v))
            ->orderByDesc('predicted_at')
            ->paginate(15)
            ->withQueryString();

        return view('prediksi.index', [
            'predictions' => $predictions,
            'filters' => $request->only(['q', 'result', 'date_from', 'date_to']),
        ]);
    }

    public function create(Request $request): View
    {
        $toddler = $request->query('balita') ? Toddler::find($request->query('balita')) : null;
        $measurement = $request->query('pemeriksaan')
            ? Measurement::with('toddler')->find($request->query('pemeriksaan'))
            : null;

        if ($measurement && ! $toddler) {
            $toddler = $measurement->toddler;
        }

        $measurements = $toddler
            ? $toddler->measurements()->orderByDesc('measurement_date')->get()
            : collect();

        return view('prediksi.create', [
            'toddlers' => Toddler::query()->orderBy('name')->get(['id', 'code', 'name', 'gender', 'birth_date']),
            'toddler' => $toddler,
            'measurements' => $measurements,
            'measurement' => $measurement,
            'health' => $this->ml->health(),
        ]);
    }

    public function store(RunPredictionRequest $request): RedirectResponse
    {
        $measurement = Measurement::with('toddler')->findOrFail($request->integer('measurement_id'));

        try {
            $prediction = $this->predictions->run($measurement, $request->user());
        } catch (MlApiException $e) {
            // Tidak ada hasil prediksi yang disimpan saat layanan gagal.
            return redirect()
                ->route('prediksi.create', ['pemeriksaan' => $measurement->id])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('prediksi.show', $prediction)
            ->with('toast', ['message' => 'Analisis risiko selesai.', 'type' => 'success']);
    }

    public function batch(): View
    {
        return view('prediksi.batch', [
            'pending' => $this->predictions->pendingQuery()->count(),
            'total' => Measurement::query()->count(),
            'health' => $this->ml->health(),
        ]);
    }

    /** Satu putaran prediksi massal; dipanggil berulang oleh halaman sampai selesai. */
    public function batchRun(Request $request): JsonResponse
    {
        $afterId = max(0, $request->integer('after_id'));

        $measurements = $this->predictions->pendingQuery()
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get();

        if ($measurements->isEmpty()) {
            return response()->json(['success' => 0, 'failed' => 0, 'errors' => [], 'last_id' => $afterId, 'remaining' => 0, 'done' => true]);
        }

        try {
            $result = $this->predictions->runBatch($measurements, $request->user());
        } catch (MlApiException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $lastId = (int) $measurements->last()->id;
        $remaining = $this->predictions->pendingQuery()->where('id', '>', $lastId)->count();

        return response()->json($result + [
            'last_id' => $lastId,
            'remaining' => $remaining,
            'done' => $remaining === 0,
        ]);
    }

    public function show(Prediction $prediction): View
    {
        $prediction->load(['toddler', 'measurement', 'modelVersion', 'predictor:id,name']);

        return view('prediksi.show', ['prediction' => $prediction]);
    }
}
