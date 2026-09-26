<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Services\ToddlerService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ToddlerService $toddlers,
    ) {}

    public function index(Request $request): View
    {
        [$type, $filters] = $this->resolve($request);

        return view('laporan.index', [
            'type' => $type,
            'types' => ReportService::types(),
            'filters' => $filters,
            'villages' => $this->toddlers->villages(),
            'posyandus' => $this->toddlers->posyandus(),
            'rows' => $this->reports->query($type, $filters)->paginate(20)->withQueryString(),
        ]);
    }

    public function print(Request $request): View
    {
        [$type, $filters] = $this->resolve($request);

        return view('laporan.print', [
            'type' => $type,
            'types' => ReportService::types(),
            'filters' => $filters,
            'rows' => $this->reports->query($type, $filters)->limit(5000)->get(),
            'printedAt' => now(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$type, $filters] = $this->resolve($request);

        [$header, $mapper] = $this->reports->csvDefinition($type);
        $query = $this->reports->query($type, $filters);
        $filename = 'laporan-'.$type.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($header, $mapper, $query): void {
            $handle = fopen('php://output', 'wb');

            // BOM agar Excel membaca UTF-8 dengan benar.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $header, ';');

            $query->chunk(500, function ($rows) use ($handle, $mapper): void {
                foreach ($rows as $row) {
                    fputcsv($handle, $mapper($row), ';');
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array{0:string, 1:array<string, mixed>} */
    private function resolve(Request $request): array
    {
        $type = $request->query('jenis', ReportService::TYPE_TODDLER);

        if (! array_key_exists($type, ReportService::types())) {
            $type = ReportService::TYPE_TODDLER;
        }

        $filters = $request->only(['gender', 'village', 'posyandu', 'result', 'date_from', 'date_to']);

        return [$type, $filters];
    }
}
