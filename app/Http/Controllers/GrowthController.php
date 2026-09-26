<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GrowthService;
use App\Services\ToddlerService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class GrowthController extends Controller
{
    public function __construct(
        private readonly GrowthService $growth,
        private readonly ToddlerService $toddlers,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'village', 'posyandu', 'weight', 'trend', 'risk']);
        $rows = $this->growth->rows($filters);

        $perPage = 20;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('perkembangan.index', [
            'rows' => new LengthAwarePaginator(
                $rows->forPage($page, $perPage)->values(),
                $rows->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()],
            ),
            'summary' => $this->growth->summary($rows),
            'filters' => $filters,
            'villages' => $this->toddlers->villages(),
            'posyandus' => $this->toddlers->posyandus(),
        ]);
    }
}
