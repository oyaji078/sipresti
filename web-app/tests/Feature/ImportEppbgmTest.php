<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MeasurementSource;
use App\Models\AuditLog;
use App\Models\ImportHistory;
use App\Models\Measurement;
use App\Models\Toddler;
use App\Services\ImportService;
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

    /** Unduhan "Excel" E-PPBGM: tabel HTML berekstensi .xls dengan markup tidak valid. */
    public function test_berkas_xls_html_eppbgm_terbaca_dan_terpetakan_otomatis(): void
    {
        Storage::fake('local');

        $html = <<<'HTML'
            <html xmlns:x="urn:schemas-microsoft-com:office:excel">
            <style>.x{}</style>
            <head><!--[if gte mso 9]><xml></xml><![endif]--></head>
            <body><table>
            <thead><caption>Data Tanggal : 2026-09-22</caption>
            <tr><th>No</th><th>NIK</th><th>Nama</th><th>JK</th><th>Tgl Lahir</th><th>BB Lahir</th>
            <th>Nama Ortu</th><th>Desa/Kel</th><th>Tanggal Pengukuran</th><th>Berat</th><th>Tinggi</th></tr>
            </thead><tbody>
            <tr><td>1.</td><td>5023184706830001&nbsp;</td><td>MUHAMAD GEDE</td><td>L</td><td>2026-05-26</td><td>3.7</td>
            <td>HIDAYATI</td><td>MONTONGTANGI   </td><td>2026-07-06</td><td>5</td><td>55</td></tr>
            </tbody></table></body></html>
            HTML;

        $this->actingAs($this->petugas())
            ->from('/import')
            ->post('/import/upload', ['file' => UploadedFile::fake()->createWithContent('Daftar-Anak.xls', $html)])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $staged = session('import.staged');

        $this->assertSame(1, $staged['total']);
        $this->assertSame('5023184706830001', $staged['rows'][0][1]);
        $this->assertSame('MONTONGTANGI', $staged['rows'][0][7]);

        $this->assertSame([
            'name' => 2, 'nik' => 1, 'birth_date' => 4, 'gender' => 3,
            'measurement_date' => 8, 'weight_kg' => 9, 'height_cm' => 10,
            'mother_name' => 6, 'village' => 7,
        ], ImportService::guessMapping($staged['header']));
    }

    /** CSV "ANSI" dari Excel Windows tidak boleh merusak sesi (serialisasi JSON). */
    public function test_csv_ansi_dikonversi_ke_utf8(): void
    {
        Storage::fake('local');

        $csv = "Nama;Desa\r\nAnanda\xA0Satu;Caf\xE9\r\n";

        $this->actingAs($this->petugas())
            ->from('/import')
            ->post('/import/upload', ['file' => UploadedFile::fake()->createWithContent('ansi.csv', $csv)])
            ->assertSessionHasNoErrors();

        $staged = session('import.staged');

        $this->assertSame(['Ananda Satu', 'Café'], $staged['rows'][0]);
        $this->assertNotFalse(json_encode($staged));
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

    public function test_baris_tidak_valid_dapat_diperbaiki_lewat_web_lalu_diimpor(): void
    {
        Storage::fake('local');
        $petugas = $this->petugas();

        $this->actingAs($petugas)->from('/import')->post('/import/upload', ['file' => $this->berkasContoh()]);
        $this->actingAs($petugas)->from('/import')->post('/import/mapping', ['mapping' => $this->pemetaanLengkap()]);

        $rusak = collect(session('import.preview')['errors'])->firstWhere('line', 5);
        $this->assertSame(['birth_date', 'gender', 'weight_kg', 'height_cm'], array_keys($rusak['problems']));
        $this->assertSame('bukan-tanggal', $rusak['values']['birth_date']);

        $this->actingAs($petugas)->from('/import')
            ->post('/import/perbaiki', ['corrections' => [5 => [
                'name' => 'Baris Diperbaiki', 'nik' => '', 'birth_date' => '2024-02-01', 'gender' => 'P',
                'measurement_date' => '01/09/2026', 'weight_kg' => '10,5', 'height_cm' => '80',
            ]]])
            ->assertRedirect('/import');

        $summary = session('import.preview')['summary'];
        $this->assertSame(4, $summary['valid']);
        $this->assertSame(0, $summary['invalid']);
        $this->assertSame(1, $summary['duplicate']);

        $this->actingAs($petugas)->from('/import')->post('/import/commit');

        $diperbaiki = Toddler::where('name', 'Baris Diperbaiki')->firstOrFail();
        $this->assertSame('P', $diperbaiki->gender);
        $this->assertSame('10.50', (string) $diperbaiki->measurements()->firstOrFail()->weight_kg);
        $this->assertDatabaseCount('measurements', 4);
    }

    public function test_perbaikan_hanya_menerima_kolom_yang_diizinkan(): void
    {
        Storage::fake('local');
        $petugas = $this->petugas();

        $this->actingAs($petugas)->from('/import')->post('/import/upload', ['file' => $this->berkasContoh()]);
        $this->actingAs($petugas)->from('/import')->post('/import/mapping', ['mapping' => $this->pemetaanLengkap()]);
        $this->actingAs($petugas)->from('/import')
            ->post('/import/perbaiki', ['corrections' => [5 => ['created_by' => '999', 'nik' => '']]]);

        $this->assertSame([5 => ['nik' => '']], session('import.corrections'));
    }

    public function test_nik_tidak_valid_dapat_dikosongkan_sekaligus(): void
    {
        Storage::fake('local');
        $petugas = $this->petugas();

        $header = 'Nama;NIK;Tgl Lahir;JK;Tanggal Pengukuran;Berat;Tinggi';
        $file = UploadedFile::fake()->createWithContent('nik.csv', implode("\r\n", [
            $header,
            'Satu;503180612220001;2024-01-15;L;2026-09-05;11;85',
            'Dua;52030116197000;2024-02-15;P;2026-09-05;10;80',
            'Tiga;;bukan-tanggal;P;2026-09-05;10;80',
        ])."\r\n");

        $this->actingAs($petugas)->from('/import')->post('/import/upload', ['file' => $file]);
        $this->actingAs($petugas)->from('/import')->post('/import/mapping', ['mapping' => [
            'name' => 0, 'nik' => 1, 'birth_date' => 2, 'gender' => 3, 'measurement_date' => 4, 'weight_kg' => 5, 'height_cm' => 6,
        ]]);
        $this->assertSame(3, session('import.preview')['summary']['invalid']);

        $this->actingAs($petugas)->from('/import')->post('/import/perbaiki', ['clear_invalid_nik' => 1]);

        $summary = session('import.preview')['summary'];
        $this->assertSame(2, $summary['valid']);
        $this->assertSame(1, $summary['invalid']);
    }

    /** @param  array<int, string>  $rows */
    private function unggahDanImpor(\App\Models\User $petugas, array $rows): void
    {
        $header = 'Nama;NIK;Tgl Lahir;JK;Tanggal Pengukuran;Berat;Tinggi;Nama Ortu;Desa/Kel;Posyandu';
        $file = UploadedFile::fake()->createWithContent('bulan.csv', implode("\r\n", [$header, ...$rows])."\r\n");

        $this->actingAs($petugas)->from('/import')->post('/import/upload', ['file' => $file]);
        $this->actingAs($petugas)->from('/import')->post('/import/mapping', ['mapping' => [
            'name' => 0, 'nik' => 1, 'birth_date' => 2, 'gender' => 3, 'measurement_date' => 4,
            'weight_kg' => 5, 'height_cm' => 6, 'mother_name' => 7, 'village' => 8, 'posyandu' => 9,
        ]]);
        $this->actingAs($petugas)->from('/import')->post('/import/commit');
    }

    public function test_ekspor_bulan_berikutnya_memperbarui_data_balita_lama(): void
    {
        Storage::fake('local');
        $petugas = $this->petugas();

        $this->unggahDanImpor($petugas, ['Ananda;5203019900010001;2024-01-15;L;2026-08-05;11;85;;Lepak;']);
        $this->unggahDanImpor($petugas, ['Ananda;5203019900010001;2024-01-15;L;2026-09-05;11,4;86;Ibu Ananda;Menceh;Melati']);

        $this->assertDatabaseCount('toddlers', 1);
        $toddler = Toddler::firstOrFail();
        $this->assertSame('Ibu Ananda', $toddler->mother_name);
        $this->assertSame('Menceh', $toddler->village);
        $this->assertSame('Melati', $toddler->posyandu);
        $this->assertSame(2, $toddler->measurements()->count());
    }

    public function test_ekspor_lama_hanya_mengisi_kolom_kosong(): void
    {
        Storage::fake('local');
        $petugas = $this->petugas();

        $this->unggahDanImpor($petugas, ['Ananda;5203019900010001;2024-01-15;L;2026-09-05;11,4;86;;Menceh;']);
        $this->unggahDanImpor($petugas, ['Ananda;5203019900010001;2024-01-15;L;2026-08-05;11;85;Ibu Ananda;Lepak;Melati']);

        $toddler = Toddler::firstOrFail();
        $this->assertSame('Menceh', $toddler->village);
        $this->assertSame('Ibu Ananda', $toddler->mother_name);
        $this->assertSame('Melati', $toddler->posyandu);
    }

    public function test_balita_tanpa_nik_dikenali_saat_nik_muncul(): void
    {
        Storage::fake('local');
        $petugas = $this->petugas();

        $this->unggahDanImpor($petugas, ['Ananda;;2024-01-15;L;2026-08-05;11;85;;;']);
        $this->unggahDanImpor($petugas, ['Ananda;5203019900010001;2024-01-15;L;2026-09-05;11,4;86;;;']);

        $this->assertDatabaseCount('toddlers', 1);
        $this->assertSame('5203019900010001', Toddler::firstOrFail()->nik);
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
