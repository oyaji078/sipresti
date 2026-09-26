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

    /** Kolom yang dapat menyebabkan baris tidak valid, sehingga dapat diperbaiki lewat web. */
    public const CORRECTABLE_FIELDS = ['name', 'nik', 'birth_date', 'gender', 'measurement_date', 'weight_kg', 'height_cm'];

    /** Nama kolom umum (ekspor E-PPBGM & template) untuk pemetaan otomatis. */
    private const HEADER_ALIASES = [
        'name' => ['nama', 'nama balita', 'nama anak'],
        'nik' => ['nik', 'nik anak', 'nik balita'],
        'birth_date' => ['tgl lahir', 'tanggal lahir', 'tanggal_lahir'],
        'gender' => ['jk', 'jenis kelamin', 'jenis_kelamin', 'l/p'],
        'measurement_date' => ['tanggal pengukuran', 'tgl pengukuran', 'tanggal_pengukuran', 'tgl ukur', 'tanggal ukur'],
        'weight_kg' => ['berat', 'bb', 'berat badan', 'berat_kg', 'bb (kg)'],
        'height_cm' => ['tinggi', 'tb', 'tinggi badan', 'panjang badan', 'tinggi_cm', 'tb (cm)'],
        'mother_name' => ['nama ortu', 'nama ibu', 'nama orang tua', 'nama_ibu'],
        'address' => ['alamat'],
        'village' => ['desa/kel', 'desa', 'kelurahan', 'desa/kelurahan'],
        'posyandu' => ['posyandu'],
    ];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array<int, string>  $header
     * @return array<string, int> field sistem => indeks kolom
     */
    public static function guessMapping(array $header): array
    {
        $normalized = array_map(static fn ($h): string => mb_strtolower(trim((string) $h)), $header);
        $mapping = [];

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($normalized as $index => $name) {
                if (in_array($name, $aliases, true)) {
                    $mapping[$field] = $index;
                    break;
                }
            }
        }

        return $mapping;
    }

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

        $header = array_shift($rows);

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
        // Unduhan "Excel" dari E-PPBGM sebenarnya tabel HTML berekstensi .xls.
        if ($this->looksLikeHtml($absolutePath)) {
            return $this->readHtmlTable($absolutePath);
        }

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

                $cells[] = $this->cleanCell($value);
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
        while (($data = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($rows === [] && isset($data[0])) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]) ?? $data[0];
            }

            $cells = array_map($this->cleanCell(...), $data);

            if (array_filter($cells, static fn ($v) => $v !== '') !== []) {
                $rows[] = $cells;
            }
        }

        fclose($handle);

        return $rows;
    }

    private function looksLikeHtml(string $absolutePath): bool
    {
        $head = (string) file_get_contents($absolutePath, false, null, 0, 1024);

        return preg_match('/^(\xEF\xBB\xBF)?\s*<(!doctype|html|table|meta)\b/i', $head) === 1;
    }

    /** @return array<int, array<int, string>> */
    private function readHtmlTable(string $absolutePath): array
    {
        $html = $this->toUtf8((string) file_get_contents($absolutePath));

        // Markup E-PPBGM tidak valid (mis. <head> salah tempat); peringatan libxml
        // diredam agar tidak diubah Laravel menjadi exception.
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $table = $dom->getElementsByTagName('table')->item(0);

        if (! $table instanceof \DOMElement) {
            return [];
        }

        $rows = [];
        foreach ($table->getElementsByTagName('tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $node) {
                if ($node instanceof \DOMElement && in_array(strtolower($node->nodeName), ['td', 'th'], true)) {
                    $cells[] = $this->cleanCell($node->textContent);
                }
            }

            if (array_filter($cells, static fn ($v) => $v !== '') !== []) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    /**
     * Semua sel dijadikan UTF-8 valid. Sel non-UTF-8 (CSV "ANSI" dari Excel
     * Windows) yang masuk ke sesi membuat serialisasi JSON sesi gagal dan
     * pengguna ikut ter-logout.
     */
    private function cleanCell(mixed $value): string
    {
        $value = $this->toUtf8((string) $value);
        $value = str_replace("\u{00A0}", ' ', $value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8')
            ? $value
            : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    // -------------------------------------------------------------- validasi
    /**
     * Validasi seluruh baris tanpa menulis apa pun ke database.
     *
     * @return array{valid:array<int,array>, errors:array<int,array>, duplicates:int, summary:array<string,int>}
     */
    /**
     * @param  array<int, array<string, string>>  $corrections  nomor baris => [field => nilai perbaikan dari web]
     */
    public function validateRows(string $path, string $extension, array $mapping, array $corrections = []): array
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

            $record = array_replace($record, $corrections[$lineNumber] ?? []);

            $problems = [];

            $name = trim((string) ($record['name'] ?? ''));
            if ($name === '') {
                $problems['name'] = 'Nama balita kosong';
            }

            $birthDate = $this->parseDate($record['birth_date'] ?? '');
            if (! $birthDate) {
                $problems['birth_date'] = 'Tanggal lahir tidak dapat dibaca';
            }

            $measurementDate = $this->parseDate($record['measurement_date'] ?? '');
            if (! $measurementDate) {
                $problems['measurement_date'] = 'Tanggal pengukuran tidak dapat dibaca';
            }

            $gender = $this->parseGender($record['gender'] ?? '');
            if (! $gender) {
                $problems['gender'] = 'Jenis kelamin tidak dikenali';
            }

            $weight = $this->parseNumber($record['weight_kg'] ?? '');
            if ($weight === null) {
                $problems['weight_kg'] = 'Berat badan tidak dapat dibaca';
            } elseif ($weight < $cfg['weight_kg_min'] || $weight > $cfg['weight_kg_max']) {
                $problems['weight_kg'] = 'Berat badan di luar batas wajar';
            }

            $height = $this->parseNumber($record['height_cm'] ?? '');
            if ($height === null) {
                $problems['height_cm'] = 'Tinggi badan tidak dapat dibaca';
            } elseif ($height < $cfg['height_cm_min'] || $height > $cfg['height_cm_max']) {
                $problems['height_cm'] = 'Tinggi badan di luar batas wajar';
            }

            $nik = preg_replace('/\D/', '', (string) ($record['nik'] ?? '')) ?: null;
            if ($nik !== null && strlen($nik) !== 16) {
                $problems['nik'] = 'NIK bukan 16 digit';
                $nik = null;
            }

            if ($birthDate && $measurementDate && $measurementDate->lt($birthDate)) {
                $problems['measurement_date'] = 'Tanggal pengukuran mendahului tanggal lahir';
            }

            if ($problems !== []) {
                $errors[] = [
                    'line' => $lineNumber,
                    'name' => $name !== '' ? $name : '(tanpa nama)',
                    'problems' => $problems,
                    'values' => array_map(
                        static fn (string $field): string => (string) ($record[$field] ?? ''),
                        array_combine(self::CORRECTABLE_FIELDS, self::CORRECTABLE_FIELDS),
                    ),
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
            $updatedToddlers = [];

            foreach ($validRows as $row) {
                $toddler = $this->resolveToddler($row, $user, $updatedToddlers);

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
                'Import E-PPBGM: '.$success.' baris masuk, '.$skipped.' duplikat dilewati, '
                    .count($updatedToddlers).' data balita diperbarui',
                $user,
            );

            $history->toddlersUpdated = count($updatedToddlers);

            return $history;
        });
    }

    /** @param  array<int, true>  $updatedToddlers  id balita yang datanya diperbarui */
    private function resolveToddler(array $row, User $user, array &$updatedToddlers): Toddler
    {
        $toddler = $row['nik'] ? Toddler::query()->where('nik', $row['nik'])->first() : null;

        // Balita yang dulu masuk tanpa NIK tetap dikenali saat NIK-nya muncul di ekspor berikutnya.
        $toddler ??= Toddler::query()
            ->where('name', $row['name'])
            ->whereDate('birth_date', $row['birth_date'])
            ->when($row['nik'], fn ($q) => $q->whereNull('nik'))
            ->first();

        if ($toddler) {
            if ($this->refreshToddler($toddler, $row)) {
                $updatedToddlers[$toddler->id] = true;
            }

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

    /**
     * Perbarui profil balita dari ekspor bulan lain. Kolom kosong selalu diisi;
     * kolom yang sudah terisi hanya ditimpa bila baris ini adalah pengukuran
     * terbaru, agar impor berkas lama tidak menimpa data yang lebih baru.
     * Tanggal lahir tidak diubah karena usia pemeriksaan lama dihitung darinya.
     */
    private function refreshToddler(Toddler $toddler, array $row): bool
    {
        $isNewest = ! $toddler->measurements()
            ->whereDate('measurement_date', '>', $row['measurement_date'])
            ->exists();

        $changes = [];

        if ($row['nik'] && blank($toddler->nik)) {
            $changes['nik'] = $row['nik'];
        }

        foreach (['name', 'gender', 'mother_name', 'address', 'village', 'posyandu'] as $field) {
            $value = $row[$field] ?? null;

            if (blank($value) || $value === $toddler->{$field}) {
                continue;
            }

            if (blank($toddler->{$field}) || $isNewest) {
                $changes[$field] = $value;
            }
        }

        if ($changes === []) {
            return false;
        }

        $toddler->update($changes);

        return true;
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
