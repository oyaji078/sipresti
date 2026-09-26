<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MeasurementSource;
use App\Models\AuditLog;
use App\Models\ImportHistory;
use App\Models\Measurement;
use App\Models\Toddler;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import data E-PPBGM.
 *
 * Struktur kolom berkas E-PPBGM TIDAK diasumsikan. Pengguna memetakan sendiri
 * kolom sumber ke kolom sistem lewat langkah Column Mapping, sehingga importer
 * tetap bekerja apapun bentuk ekspor yang diberikan puskesmas.
 */
class ImportService
{
    /** Kolom sistem yang dapat dipetakan. */
    public const FIELDS = [
        'name' => ['label' => 'Nama Balita', 'required' => true],
        'nik' => ['label' => 'NIK', 'required' => false],
        'birth_date' => ['label' => 'Tanggal Lahir', 'required' => true],
        'gender' => ['label' => 'Jenis Kelamin', 'required' => true],
        'measurement_date' => ['label' => 'Tanggal Pengukuran', 'required' => true],
        'weight_kg' => ['label' => 'Berat Badan (kg)', 'required' => true],
        'height_cm' => ['label' => 'Tinggi/Panjang Badan (cm)', 'required' => true],
        'mother_name' => ['label' => 'Nama Ibu', 'required' => false],
        'address' => ['label' => 'Alamat', 'required' => false],
        'village' => ['label' => 'Desa', 'required' => false],
        'posyandu' => ['label' => 'Posyandu', 'required' => false],
    ];

    public function __construct(private readonly AuditService $audit) {}

    // ------------------------------------------------------------------ baca
    /**
     * Simpan berkas unggahan dengan nama acak (bukan nama asli) lalu baca
     * header dan beberapa baris pertama untuk pratinjau.
     *
     * @return array{path:string, header:array<int,string>, rows:array<int,array<int,string>>, total:int}
     */
    public function stage(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid()->toString().'.'.$extension;

        // Disimpan di storage privat, di luar document root, dan tidak dieksekusi.
        $path = $file->storeAs('imports', $storedName, 'local');

        $rows = $this->readRows(Storage::disk('local')->path($path), $extension);

        if ($rows === []) {
            throw new \RuntimeException('Berkas kosong atau tidak dapat dibaca.');
        }

        $header = array_map(
            static fn ($v): string => trim((string) $v),
            array_shift($rows)
        );

        return [
            'path' => $path,
            'stored_name' => $storedName,
            'header' => $header,
            'rows' => array_slice($rows, 0, 10),
            'total' => count($rows),
        ];
    }

    /** @return array<int, array<int, string>> */
    private function readRows(string $absolutePath, string $extension): array
    {
        if ($extension === 'csv') {
            return $this->readCsv($absolutePath);
        }

        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            $iterator = $row->getCellIterator();
            $iterator->setIterateOnlyExistingCells(false);

            foreach ($iterator as $cell) {
                $value = $cell->getValue();

                // Tanggal Excel tersimpan sebagai angka serial.
                if (ExcelDate::isDateTime($cell) && is_numeric($value)) {
                    $value = ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
                }

                $cells[] = is_null($value) ? '' : trim((string) $value);
            }

            if (array_filter($cells, static fn ($v) => $v !== '') !== []) {
                $rows[] = $cells;
            }
        }

        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    /** @return array<int, array<int, string>> */
    private function readCsv(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            return [];
        }

        $firstLine = (string) fgets($handle);
        rewind($handle);

        // Deteksi pemisah: E-PPBGM kerap memakai titik koma.
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $cells = array_map(static fn ($v): string => trim((string) $v), $data);

            if (array_filter($cells, static fn ($v) => $v !== '') !== []) {
                $rows[] = $cells;
            }
        }

        fclose($handle);

        if ($rows !== []) {
            // Buang BOM pada sel pertama bila ada.
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]) ?? $rows[0][0];
        }

        return $rows;
    }

    // -------------------------------------------------------------- validasi
    /**
     * Validasi seluruh baris tanpa menulis apa pun ke database.
     *
     * @return array{valid:array<int,array>, errors:array<int,array>, duplicates:int, summary:array<string,int>}
     */
    public function validateRows(string $path, string $extension, array $mapping): array
    {
        $rows = $this->readRows(Storage::disk('local')->path($path), $extension);
        array_shift($rows);

        $cfg = config('sipresti.measurement');
        $valid = [];
        $errors = [];
        $duplicates = 0;
        $seen = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // +1 header, +1 karena manusia menghitung dari 1
            $record = [];

            foreach ($mapping as $field => $columnIndex) {
                if ($columnIndex === null || $columnIndex === '') {
                    continue;
                }
                $record[$field] = $row[(int) $columnIndex] ?? '';
            }

            $problems = [];

            $name = trim((string) ($record['name'] ?? ''));
            if ($name === '') {
                $problems[] = 'Nama balita kosong';
            }

            $birthDate = $this->parseDate($record['birth_date'] ?? '');
            if (! $birthDate) {
                $problems[] = 'Tanggal lahir tidak dapat dibaca';
            }

            $measurementDate = $this->parseDate($record['measurement_date'] ?? '');
            if (! $measurementDate) {
                $problems[] = 'Tanggal pengukuran tidak dapat dibaca';
            }

            $gender = $this->parseGender($record['gender'] ?? '');
            if (! $gender) {
                $problems[] = 'Jenis kelamin tidak dikenali';
            }

            $weight = $this->parseNumber($record['weight_kg'] ?? '');
            if ($weight === null) {
                $problems[] = 'Berat badan tidak dapat dibaca';
            } elseif ($weight < $cfg['weight_kg_min'] || $weight > $cfg['weight_kg_max']) {
                $problems[] = 'Berat badan di luar batas wajar';
            }

            $height = $this->parseNumber($record['height_cm'] ?? '');
            if ($height === null) {
                $problems[] = 'Tinggi badan tidak dapat dibaca';
            } elseif ($height < $cfg['height_cm_min'] || $height > $cfg['height_cm_max']) {
                $problems[] = 'Tinggi badan di luar batas wajar';
            }

            $nik = preg_replace('/\D/', '', (string) ($record['nik'] ?? '')) ?: null;
            if ($nik !== null && strlen($nik) !== 16) {
                $problems[] = 'NIK bukan 16 digit';
                $nik = null;
            }

            if ($birthDate && $measurementDate && $measurementDate->lt($birthDate)) {
                $problems[] = 'Tanggal pengukuran mendahului tanggal lahir';
            }

            if ($problems !== []) {
                $errors[] = [
                    'line' => $lineNumber,
                    'name' => $name !== '' ? $name : '(tanpa nama)',
                    'problems' => $problems,
                ];
                continue;
            }

            $key = ($nik ?: Str::slug($name).'|'.$birthDate->toDateString()).'|'.$measurementDate->toDateString();

            if (isset($seen[$key])) {
                $duplicates++;
                $errors[] = [
                    'line' => $lineNumber,
                    'name' => $name,
                    'problems' => ['Duplikat di dalam berkas (baris '.$seen[$key].')'],
                ];
                continue;
            }
            $seen[$key] = $lineNumber;

            $valid[] = [
                'line' => $lineNumber,
                'name' => $name,
                'nik' => $nik,
                'birth_date' => $birthDate->toDateString(),
                'gender' => $gender,
                'measurement_date' => $measurementDate->toDateString(),
                'weight_kg' => $weight,
                'height_cm' => $height,
                'mother_name' => $this->str($record['mother_name'] ?? null, 150),
                'address' => $this->str($record['address'] ?? null, 255),
                'village' => $this->str($record['village'] ?? null, 100),
                'posyandu' => $this->str($record['posyandu'] ?? null, 100),
            ];
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'duplicates' => $duplicates,
            'summary' => [
                'total' => count($rows),
                'valid' => count($valid),
                'invalid' => count($errors) - $duplicates,
                'duplicate' => $duplicates,
            ],
        ];
    }

    // ---------------------------------------------------------------- commit
    /**
     * Tulis data valid ke database di dalam satu transaction.
     * Bila ada kegagalan di tengah jalan, seluruh batch dibatalkan.
     */
    public function commit(array $validRows, array $meta, array $mapping, User $user): ImportHistory
    {
        return DB::transaction(function () use ($validRows, $meta, $mapping, $user): ImportHistory {
            $history = ImportHistory::create([
                'user_id' => $user->id,
                'original_name' => $meta['original_name'],
                'stored_name' => $meta['stored_name'] ?? null,
                'file_hash' => $meta['file_hash'] ?? null,
                'total_rows' => $meta['total'] ?? count($validRows),
                'success_rows' => 0,
                'failed_rows' => $meta['invalid'] ?? 0,
                'duplicate_rows' => $meta['duplicate'] ?? 0,
                'invalid_rows' => $meta['invalid'] ?? 0,
                'column_mapping_json' => $mapping,
                'error_report_json' => array_slice($meta['errors'] ?? [], 0, 200),
                'status' => ImportHistory::STATUS_PENDING,
            ]);

            $success = 0;
            $skipped = 0;

            foreach ($validRows as $row) {
                $toddler = $this->resolveToddler($row, $user);

                $exists = Measurement::query()
                    ->where('toddler_id', $toddler->id)
                    ->whereDate('measurement_date', $row['measurement_date'])
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                Measurement::create([
                    'toddler_id' => $toddler->id,
                    'measurement_date' => $row['measurement_date'],
                    'age_months' => Measurement::calculateAgeMonths(
                        $toddler->birth_date,
                        Carbon::parse($row['measurement_date'])
                    ),
                    'weight_kg' => $row['weight_kg'],
                    'height_cm' => $row['height_cm'],
                    'source' => MeasurementSource::IMPORT_EPPBGM->value,
                    'import_history_id' => $history->id,
                    'created_by' => $user->id,
                ]);

                $success++;
            }

            $history->update([
                'success_rows' => $success,
                'duplicate_rows' => $history->duplicate_rows + $skipped,
                'status' => ImportHistory::STATUS_COMMITTED,
            ]);

            $this->audit->log(
                AuditLog::ACTION_IMPORT,
                'import_history',
                $history->id,
                'Import E-PPBGM: '.$success.' baris masuk, '.$skipped.' duplikat dilewati',
                $user,
            );

            return $history;
        });
    }

    private function resolveToddler(array $row, User $user): Toddler
    {
        $query = Toddler::query();

        if ($row['nik']) {
            $query->where('nik', $row['nik']);
        } else {
            $query->where('name', $row['name'])->whereDate('birth_date', $row['birth_date']);
        }

        $toddler = $query->first();

        if ($toddler) {
            return $toddler;
        }

        return Toddler::create([
            'code' => Toddler::nextCode(),
            'nik' => $row['nik'],
            'name' => $row['name'],
            'birth_date' => $row['birth_date'],
            'gender' => $row['gender'],
            'mother_name' => $row['mother_name'],
            'address' => $row['address'],
            'village' => $row['village'],
            'posyandu' => $row['posyandu'],
            'created_by' => $user->id,
        ]);
    }

    // --------------------------------------------------------------- parsing
    private function parseDate(mixed $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // Angka serial Excel.
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 60000) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            } catch (\Throwable) {
                return null;
            }
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d', 'm/d/Y', 'Y-m-d H:i:s', 'd/m/Y H:i'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date && $date->year > 1990 && $date->year < 2100) {
                    return $date->startOfDay();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function parseGender(mixed $value): ?string
    {
        $value = mb_strtoupper(trim((string) $value));

        if ($value === '') {
            return null;
        }

        return match (true) {
            in_array($value, ['L', 'LK', 'LAKI-LAKI', 'LAKI LAKI', 'LAKI', 'M', 'MALE', '1'], true) => 'L',
            in_array($value, ['P', 'PR', 'PEREMPUAN', 'F', 'FEMALE', '2', '0'], true) => 'P',
            default => null,
        };
    }

    private function parseNumber(mixed $value): ?float
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = str_replace(' ', '', $value);

        // Angka Indonesia memakai koma sebagai pemisah desimal.
        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function str(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
