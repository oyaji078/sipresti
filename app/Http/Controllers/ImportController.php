<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportEppbgmRequest;
use App\Models\ImportHistory;
use App\Services\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    private const SESSION_KEY = 'import.staged';

    public function __construct(private readonly ImportService $import) {}

    public function index(Request $request): View
    {
        return view('import.index', [
            'fields' => ImportService::FIELDS,
            'staged' => $request->session()->get(self::SESSION_KEY),
            'preview' => $request->session()->get('import.preview'),
            'histories' => ImportHistory::query()
                ->with('user:id,name')
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    /** Langkah 1–2: Upload lalu tampilkan pratinjau berkas. */
    public function upload(ImportEppbgmRequest $request): RedirectResponse
    {
        $file = $request->file('file');

        try {
            $staged = $this->import->stage($file);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Berkas tidak dapat dibaca. Pastikan format CSV atau XLSX yang benar.');
        }

        $request->session()->put(self::SESSION_KEY, [
            'path' => $staged['path'],
            'stored_name' => $staged['stored_name'],
            'extension' => strtolower($file->getClientOriginalExtension()),
            'original_name' => $file->getClientOriginalName(),
            'file_hash' => hash_file('sha256', $file->getRealPath()),
            'header' => $staged['header'],
            'rows' => $staged['rows'],
            'total' => $staged['total'],
        ]);

        $request->session()->forget('import.preview');

        return back()->with('toast', [
            'message' => 'Berkas terbaca: '.$staged['total'].' baris data. Lanjutkan ke pemetaan kolom.',
            'type' => 'info',
        ]);
    }

    /** Langkah 3–5: Pemetaan kolom, validasi, tampilkan baris bermasalah. */
    public function mapping(Request $request): RedirectResponse
    {
        $staged = $request->session()->get(self::SESSION_KEY);

        if (! $staged) {
            return redirect()->route('import.index')->with('error', 'Sesi import telah berakhir. Unggah ulang berkas.');
        }

        $mapping = $request->input('mapping', []);

        foreach (ImportService::FIELDS as $field => $meta) {
            if ($meta['required'] && ($mapping[$field] ?? '') === '') {
                return back()->with('error', 'Kolom wajib "'.$meta['label'].'" belum dipetakan.');
            }
        }

        $mapping = array_filter($mapping, static fn ($v) => $v !== '' && $v !== null);

        try {
            $result = $this->import->validateRows($staged['path'], $staged['extension'], $mapping);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Validasi gagal. Periksa kembali berkas dan pemetaan kolom.');
        }

        $request->session()->put('import.preview', [
            'mapping' => $mapping,
            'summary' => $result['summary'],
            'errors' => array_slice($result['errors'], 0, 100),
            'sample' => array_slice($result['valid'], 0, 10),
        ]);

        return back()->with('toast', [
            'message' => 'Validasi selesai: '.$result['summary']['valid'].' baris siap diimpor.',
            'type' => $result['summary']['valid'] > 0 ? 'success' : 'warning',
        ]);
    }

    /** Langkah 6–8: Konfirmasi, tulis dalam transaction, tampilkan ringkasan. */
    public function commit(Request $request): RedirectResponse
    {
        $staged = $request->session()->get(self::SESSION_KEY);
        $preview = $request->session()->get('import.preview');

        if (! $staged || ! $preview) {
            return redirect()->route('import.index')->with('error', 'Sesi import telah berakhir. Unggah ulang berkas.');
        }

        try {
            $result = $this->import->validateRows($staged['path'], $staged['extension'], $preview['mapping']);

            if ($result['valid'] === []) {
                return back()->with('error', 'Tidak ada baris valid untuk diimpor.');
            }

            $history = $this->import->commit(
                $result['valid'],
                [
                    'original_name' => $staged['original_name'],
                    'stored_name' => $staged['stored_name'],
                    'file_hash' => $staged['file_hash'],
                    'total' => $result['summary']['total'],
                    'invalid' => $result['summary']['invalid'],
                    'duplicate' => $result['summary']['duplicate'],
                    'errors' => $result['errors'],
                ],
                $preview['mapping'],
                $request->user(),
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Import dibatalkan karena terjadi kesalahan. Tidak ada data yang tersimpan.');
        }

        // Berkas sementara dihapus setelah diproses.
        Storage::disk('local')->delete($staged['path']);
        $request->session()->forget([self::SESSION_KEY, 'import.preview']);

        return redirect()->route('import.show', $history)
            ->with('toast', ['message' => 'Import selesai: '.$history->success_rows.' data pemeriksaan masuk.', 'type' => 'success']);
    }

    public function show(ImportHistory $importHistory): View
    {
        $importHistory->load('user:id,name');

        return view('import.show', ['history' => $importHistory]);
    }

    /** Berkas contoh agar petugas tahu bentuk minimal yang dapat diimpor. */
    public function template(): StreamedResponse
    {
        $headers = ['nama', 'nik', 'tanggal_lahir', 'jenis_kelamin', 'tanggal_pengukuran', 'berat_kg', 'tinggi_cm', 'nama_ibu', 'alamat', 'desa', 'posyandu'];

        return response()->streamDownload(function () use ($headers): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, ';');
            fputcsv($handle, ['Contoh Nama', '', '2023-05-12', 'L', '2026-09-01', '9.2', '79.0', '', '', '', ''], ';');
            fclose($handle);
        }, 'template-import-eppbgm.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
