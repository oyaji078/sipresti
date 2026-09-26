<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreToddlerRequest;
use App\Http\Requests\UpdateToddlerRequest;
use App\Models\Toddler;
use App\Services\ToddlerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ToddlerController extends Controller
{
    public function __construct(private readonly ToddlerService $toddlers) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['gender', 'village', 'posyandu']);

        $toddlers = Toddler::query()
            ->with(['latestMeasurement', 'latestPrediction'])
            ->search($request->query('q'))
            ->filter($filters)
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('balita.index', [
            'toddlers' => $toddlers,
            'villages' => $this->toddlers->villages(),
            'posyandus' => $this->toddlers->posyandus(),
            'filters' => $filters + ['q' => $request->query('q')],
        ]);
    }

    public function create(): View
    {
        return view('balita.create', [
            'nextCode' => Toddler::nextCode(),
            'villages' => $this->toddlers->villages(),
            'posyandus' => $this->toddlers->posyandus(),
        ]);
    }

    public function store(StoreToddlerRequest $request): RedirectResponse
    {
        $toddler = $this->toddlers->create($request->validated(), $request->user());

        return redirect()
            ->route('balita.show', $toddler)
            ->with('toast', ['message' => 'Data balita '.$toddler->code.' berhasil disimpan.', 'type' => 'success']);
    }

    public function show(Toddler $toddler): View
    {
        $toddler->load(['creator:id,name']);

        $measurements = $toddler->measurements()
            ->with('creator:id,name')
            ->orderByDesc('measurement_date')
            ->paginate(10, ['*'], 'pemeriksaan');

        $predictions = $toddler->predictions()
            ->with(['modelVersion:id,version', 'predictor:id,name', 'measurement:id,age_months,weight_kg,height_cm'])
            ->orderByDesc('predicted_at')
            ->paginate(10, ['*'], 'prediksi');

        return view('balita.show', [
            'toddler' => $toddler,
            'measurements' => $measurements,
            'predictions' => $predictions,
            'latest' => $toddler->measurements()->orderByDesc('measurement_date')->first(),
            'growth' => $this->growthSeries($toddler),
        ]);
    }

    public function edit(Toddler $toddler): View
    {
        return view('balita.edit', [
            'toddler' => $toddler,
            'villages' => $this->toddlers->villages(),
            'posyandus' => $this->toddlers->posyandus(),
        ]);
    }

    public function update(UpdateToddlerRequest $request, Toddler $toddler): RedirectResponse
    {
        $this->toddlers->update($toddler, $request->validated(), $request->user());

        return redirect()
            ->route('balita.show', $toddler)
            ->with('toast', ['message' => 'Data balita '.$toddler->code.' berhasil diperbarui.', 'type' => 'success']);
    }

    public function destroy(Request $request, Toddler $toddler): RedirectResponse
    {
        $result = $this->toddlers->delete($toddler, $request->user());

        if (! $result['deleted']) {
            return redirect()->route('balita.show', $toddler)->with('error', $result['message']);
        }

        return redirect()->route('balita.index')
            ->with('toast', ['message' => $result['message'], 'type' => 'success']);
    }

    public function growthData(Toddler $toddler): JsonResponse
    {
        return response()->json($this->growthSeries($toddler));
    }

    /** @return array{labels: array<int,string>, weight: array<int,float>, height: array<int,float>, age: array<int,int>} */
    private function growthSeries(Toddler $toddler): array
    {
        $rows = $toddler->measurements()
            ->orderBy('measurement_date')
            ->get(['measurement_date', 'age_months', 'weight_kg', 'height_cm']);

        return [
            'labels' => $rows->map(fn ($r) => $r->measurement_date->format('d/m/y'))->all(),
            'age' => $rows->map(fn ($r) => (int) $r->age_months)->all(),
            'weight' => $rows->map(fn ($r) => (float) $r->weight_kg)->all(),
            'height' => $rows->map(fn ($r) => (float) $r->height_cm)->all(),
        ];
    }
}
