<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MeasurementSource;
use App\Models\AuditLog;
use App\Models\ImportHistory;
use App\Models\Measurement;
use App\Models\Toddler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportEppbgmTest extends TestCase
{
    use RefreshDatabase;

    /** Meniru ekspor E-PPBGM: nama kolom bebas, tanggal dd/mm/yyyy, desimal koma. */
    private function berkasContoh(): UploadedFile
    {
        $rows = [
            'Nama Anak;NIK Anak;Tgl Lahir;JK;Tgl Ukur;BB (Kg);TB (Cm);Nama Ibu;Desa;Posyandu',
            'Ananda Satu;5203019900010001;15/01/2024;Laki-laki;01/09/2026;11,2;85,4;Ibu Satu;Lepak;Melati',
            'Ananda Dua;5203019900010002;20/06/2023;Perempuan;01/09/2026;13,8;92,1;Ibu Dua;Menceh;Mawar',
            'Ananda Tiga;;10/03/2025;P;01/09/2026;9,1;74,5;Ibu Tiga;Lepak;Anggrek',
            'Baris Rusak;;bukan-tanggal;X;01/09/2026;abc;xyz;;;',
            'Ananda Satu;5203019900010001;15/01/2024;Laki-laki;01/09/2026;11,2;85,4;Ibu Satu;Lepak;Melati',
        ];

        return UploadedFile::fake()->createWithContent('eppbgm.csv', implode("\r\n", $rows)."\r\n");
    }

    /** @return array<string, int|string> */
    private function pemetaanLengkap(): array
    {
        return [
            'name' => 0, 'nik' => 1, 'birth_date' => 2, 'gender' => 3,
            'measurement_date' => 4, 'weight_kg' => 5, 'height_cm' => 6,
            'mother_name' => 7, 'village' => 8, 'posyandu' => 9,
        ];
    }

    public function test_unggah_berkas_menampilkan_pratinjau_kolom(): void
    {
        Storage::fake('local');

        $this->actingAs($this->petugas())
            ->from('/import')
            ->post('/import/upload', ['file' => $this->berkasContoh()])
            ->assertRedirect('/import')
            ->assertSessionHas('import.staged');

        $staged = session('import.staged');

        $this->assertSame('eppbgm.csv', $staged['original_name']);
        $this->assertSame(5, $staged['total']);
        $this->assertContains('Nama Anak', $staged['header']);
        $this->assertContains('BB (Kg)', $staged['header']);
    }

    public function test_berkas_csv_asli_tidak_ditolak_karena_tipe_mime(): void
    {
        Storage::fake('local');

        $this->actingAs($this->petugas())
            ->from('/import')
            ->post('/import/upload', ['file' => $this->berkasContoh()])
            ->assertSessionHasNoErrors();
    }

    public function test_berkas_berekstensi_terlarang_ditolak(): void
    {
        Storage::fake('local');

        $this->actingAs($this->petugas())
            ->from('/import')
            ->post('/import/upload', ['file' => UploadedFile::fake()->createWithContent('jahat.php', '<?php echo 1;')])
            ->assertSessionHasErrors('file');
    }

    public function test_validasi_menghitung_baris_valid_invalid_dan_duplikat(): void
    {
        Storage::fake('local');
        $petugas = $this->petugas();

        $this->actingAs($petugas)->from('/import')->post('/import/upload', ['file' => $this->berkasContoh()]);

        $this->actingAs($petugas)->from('/import')
            ->post('/import/mapping', ['mapping' => $this->pemetaanLengkap()])
            ->assertRedirect('/import')
            ->assertSessionHas('import.preview');

        $summary = session('import.preview')['summary'];

        $this->assertSame(5, $summary['total']);
        $this->assertSame(3, $summary['valid']);
        $this->assertSame(1, $summary['invalid']);
        $this->assertSame(1, $summary['duplicate']);

        // Tidak ada yang ditulis ke database sebelum konfirmasi.
        $this->assertDatabaseCount('toddlers', 0);
        $this->assertDatabaseCount('measurements', 0);
    }

    public function test_kolom_wajib_belum_dipetakan_ditolak(): void
    {
        Storage::fake('local');
        $petugas = $this->petugas();

        $this->actingAs($petugas)->from('/import')->post('/import/upload', ['file' => $this->berkasContoh()]);

        $mapping = $this->pemetaanLengkap();
        unset($mapping['weight_kg']);

        $this->actingAs($petugas)->from('/import')
            ->post('/import/mapping', ['mapping' => $mapping])
            ->assertSessionHas('error');

        $this->assertNull(session('import.preview'));
    }

    public function test_commit_menulis_data_dan_menghitung_usia(): void
    {
        Storage::fake('local');
        $petugas = $this->petugas();

        $this->actingAs($petugas)->from('/import')->post('/import/upload', ['file' => $this->berkasContoh()]);
        $this->actingAs($petugas)->from('/import')->post('/import/mapping', ['mapping' => $this->pemetaanLengkap()]);
        $this->actingAs($petugas)->from('/import')->post('/import/commit')->assertRedirect();

        $this->assertDatabaseCount('toddlers', 3);
        $this->assertDatabaseCount('measurements', 3);

        $satu = Toddler::where('name', 'Ananda Satu')->firstOrFail();
        $this->assertSame('L', $satu->gender);
        $this->assertSame('5203019900010001', $satu->nik);
        $this->assertSame('Lepak', $satu->village);
        $this->assertSame('2024-01-15', $satu->birth_date->toDateString());

        $measurement = $satu->measurements()->firstOrFail();
        $this->assertSame(MeasurementSource::IMPORT_EPPBGM, $measurement->source);
        $this->assertSame('11.20', (string) $measurement->weight_kg);
        $this->assertSame('85.4', (string) $measurement->height_cm);
        // 15 Jan 2024 -> 1 Sep 2026 = 31 bulan.
        $this->assertSame(31, $measurement->age_months);

        $dua = Toddler::where('name', 'Ananda Dua')->firstOrFail();
        $this->assertSame('P', $dua->gender);

        $history = ImportHistory::firstOrFail();
        $this->assertSame(ImportHistory::STATUS_COMMITTED, $history->status);
        $this->assertSame(3, $history->success_rows);
        $this->assertSame(1, $history->invalid_rows);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditLog::ACTION_IMPORT]);
    }

    public function test_import_menautkan_pemeriksaan_ke_batch(): void
    {
        Storage::fake('local');
        $petugas = $this->petugas();

        $this->actingAs($petugas)->from('/import')->post('/import/upload', ['file' => $this->berkasContoh()]);
        $this->actingAs($petugas)->from('/import')->post('/import/mapping', ['mapping' => $this->pemetaanLengkap()]);
        $this->actingAs($petugas)->from('/import')->post('/import/commit');

        $history = ImportHistory::firstOrFail();

        $this->assertSame(3, Measurement::where('import_history_id', $history->id)->count());
    }

    public function test_import_kedua_tidak_menduplikasi_balita_maupun_pemeriksaan(): void
    {
        Storage::fake('local');
        $petugas = $this->petugas();

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($petugas)->from('/import')->post('/import/upload', ['file' => $this->berkasContoh()]);
            $this->actingAs($petugas)->from('/import')->post('/import/mapping', ['mapping' => $this->pemetaanLengkap()]);
            $this->actingAs($petugas)->from('/import')->post('/import/commit');
        }

        $this->assertDatabaseCount('toddlers', 3);
        $this->assertDatabaseCount('measurements', 3);

        $kedua = ImportHistory::orderByDesc('id')->firstOrFail();
        $this->assertSame(0, $kedua->success_rows);
        $this->assertGreaterThan(0, $kedua->duplicate_rows);
    }

    public function test_commit_tanpa_sesi_diarahkan_ulang(): void
    {
        $this->actingAs($this->petugas())
            ->post('/import/commit')
            ->assertRedirect('/import')
            ->assertSessionHas('error');
    }

    public function test_template_import_dapat_diunduh(): void
    {
        $this->actingAs($this->petugas())
            ->get('/import-template')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
