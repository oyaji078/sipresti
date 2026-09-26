<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeasurementRequest;
use App\Http\Requests\UpdateMeasurementRequest;
use App\Models\Measurement;
use App\Models\Toddler;
use App\Services\MeasurementService;
use App\Services\ToddlerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeasurementController extends Controller
{
    public function __construct(
        private readonly MeasurementService $measurements,
        private readonly ToddlerService $toddlers,
    ) {}

    public function index(Request $request): View
    {
        $measurements = Measurement::query()
            ->with(['toddler:id,code,name,gender,village,posyandu', 'creator:id,name'])
            ->when($request->query('q'), function ($query, $term): void {
                $query->whereHas('toddler', fn ($q) => $q->search($term));
            })
            ->when($request->query('source'), fn ($q, $v) => $q->where('source', $v))
            ->when($request->query('date_from'), fn ($q, $v) => $q->whereDate('measurement_date', '>=', $v))
            ->when($request->query('date_to'), fn ($q, $v) => $q->whereDate('measurement_date', '<=', $v))
            ->orderByDesc('measurement_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('pemeriksaan.index', [
            'measurements' => $measurements,
            'filters' => $request->only(['q', 'source', 'date_from', 'date_to']),
        ]);
    }

    public function create(Request $request): View
    {
        $selected = $request->query('balita')
            ? Toddler::find($request->query('balita'))
            : null;

        return view('pemeriksaan.create', [
            'toddlers' => Toddler::query()->orderBy('name')->get(['id', 'code', 'name', 'birth_date', 'gender']),
            'selected' => $selected,
        ]);
    }

    public function store(StoreMeasurementRequest $request): RedirectResponse
    {
        $measurement = $this->measurements->create($request->validated(), $request->user());

        return redirect()
            ->route('pemeriksaan.show', $measurement)
            ->with('toast', ['message' => 'Data pemeriksaan berhasil disimpan.', 'type' => 'success']);
    }

    public function show(Measurement $measurement): View
    {
        $measurement->load([
            'toddler',
            'creator:id,name',
            'predictions' => fn ($q) => $q->with(['modelVersion:id,version', 'predictor:id,name'])->orderByDesc('predicted_at'),
        ]);

        return view('pemeriksaan.show', ['measurement' => $measurement]);
    }

    public function edit(Measurement $measurement): View
    {
        $measurement->load('toddler');

        return view('pemeriksaan.edit', ['measurement' => $measurement]);
    }

    public function update(UpdateMeasurementRequest $request, Measurement $measurement): RedirectResponse
    {
        $this->measurements->update($measurement, $request->validated(), $request->user());

        return redirect()
            ->route('pemeriksaan.show', $measurement)
            ->with('toast', ['message' => 'Data pemeriksaan berhasil diperbarui.', 'type' => 'success']);
    }

    public function destroy(Request $request, Measurement $measurement): RedirectResponse
    {
        $toddlerId = $measurement->toddler_id;
        $result = $this->measurements->delete($measurement, $request->user());

        if (! $result['deleted']) {
            return redirect()->route('pemeriksaan.show', $measurement)->with('error', $result['message']);
        }

        return redirect()->route('balita.show', $toddlerId)
            ->with('toast', ['message' => $result['message'], 'type' => 'success']);
    }
}
