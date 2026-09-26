# Pengujian SIPRESTI

Empat lapis pengujian: **otomatis (unit/feature/API)**, **Black Box**,
**ML validation**, dan **User Acceptance Test**.

---

## 1. Menjalankan Pengujian Otomatis

### 1.1 Aplikasi Web

Buat basis data uji lebih dulu:

```sql
CREATE DATABASE sipresti_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
cd web-app
php artisan test                        # seluruh test
php artisan test --filter=PredictionTest
```

Pengujian memakai MySQL, bukan SQLite in-memory, karena agregasi dashboard memakai
`DATE_FORMAT` milik MySQL dan skema produksi juga MySQL. Panggilan ke ML API
di-*fake* dengan `Http::fake()` — tidak ada permintaan jaringan sungguhan.

### 1.2 ML API

```bash
cd ml-api
ML_API_KEY=kunci-uji-untuk-pengujian-otomatis .venv/Scripts/python.exe -m pytest tests -q
```

### 1.3 Hasil Terakhir

| Suite | Berkas | Jumlah | Hasil |
|---|---|---|---|
| Autentikasi | `tests/Feature/AuthTest.php` | 11 | ✅ lulus |
| Peran & otorisasi | `tests/Feature/RolePermissionTest.php` | 28 | ✅ lulus |
| CRUD balita | `tests/Feature/ToddlerCrudTest.php` | 13 | ✅ lulus |
| Antropometri | `tests/Feature/MeasurementTest.php` | 12 | ✅ lulus |
| Integrasi prediksi | `tests/Feature/PredictionTest.php` | 13 | ✅ lulus |
| Import E-PPBGM | `tests/Feature/ImportEppbgmTest.php` | 10 | ✅ lulus |
| Dashboard & laporan | `tests/Feature/DashboardReportTest.php` | 11 | ✅ lulus |
| Admin & profil | `tests/Feature/AdminTest.php` | 17 | ✅ lulus |
| **Total web** | | **115 test, 366 assertion** | **✅ lulus** |
| ML API | `ml-api/tests/test_api.py` | 40 | ✅ lulus |

Angka di atas diambil dari eksekusi nyata. Perbarui tabel ini hanya setelah
menjalankan ulang pengujian.

---

## 2. Matriks Black Box Testing

Isi kolom **Hasil** hanya setelah skenario benar-benar dijalankan secara manual di
peramban. Jangan menandai LULUS sebelum diuji.

### 2.1 Autentikasi

| No | Skenario | Masukan | Keluaran yang Diharapkan | Hasil |
|---|---|---|---|---|
| A-01 | Login valid (username) | username + kata sandi benar | Masuk ke dashboard sesuai peran | ☐ |
| A-02 | Login valid (email) | email + kata sandi benar | Masuk ke dashboard | ☐ |
| A-03 | Kata sandi salah | kata sandi keliru | Pesan "Username/email atau kata sandi tidak sesuai" | ☐ |
| A-04 | Akun tidak terdaftar | username acak | Pesan kesalahan yang sama, tanpa membocorkan akun mana yang ada | ☐ |
| A-05 | Isian kosong | keduanya kosong | Pesan "wajib diisi" pada kedua kolom | ☐ |
| A-06 | Akun nonaktif | akun status INACTIVE | Pesan "Akun Anda tidak aktif" | ☐ |
| A-07 | Batas percobaan | 6 kali gagal | Pesan "Terlalu banyak percobaan login" | ☐ |
| A-08 | Logout | tekan Logout | Kembali ke halaman login, tombol Back tidak membuka dashboard | ☐ |
| A-09 | Akses tanpa login | buka `/dashboard` langsung | Dialihkan ke `/login` | ☐ |

### 2.2 Otorisasi Peran

| No | Skenario | Masukan | Keluaran yang Diharapkan | Hasil |
|---|---|---|---|---|
| R-01 | Petugas buka area admin | `/admin/users` | Halaman 403 | ☐ |
| R-02 | Admin buka form tambah balita | `/balita-tambah` | Halaman 403 | ☐ |
| R-03 | Admin coba jalankan prediksi | POST `/prediksi` | 403, tidak ada baris prediksi baru | ☐ |
| R-04 | Menu sesuai peran | login sebagai masing-masing peran | Sidebar hanya menampilkan menu yang berhak | ☐ |
| R-05 | Admin melihat data klinis | `/balita`, `/pemeriksaan` | Dapat melihat, tanpa tombol ubah/hapus | ☐ |

### 2.3 Data Balita

| No | Skenario | Masukan | Keluaran yang Diharapkan | Hasil |
|---|---|---|---|---|
| B-01 | Tambah balita valid | data lengkap | Tersimpan, kode `BLT-0000xx` terbentuk otomatis | ☐ |
| B-02 | Nama kosong | nama dikosongkan | Pesan "nama balita wajib diisi" | ☐ |
| B-03 | Tanggal lahir masa depan | tanggal > hari ini | Pesan penolakan | ☐ |
| B-04 | NIK bukan 16 digit | `123` | Pesan "NIK harus terdiri dari 16 digit angka" | ☐ |
| B-05 | NIK duplikat | NIK yang sudah ada | Pesan "NIK ini sudah terdaftar" | ☐ |
| B-06 | Ubah balita | ubah nama & desa | Perubahan tersimpan | ☐ |
| B-07 | Hapus balita tanpa pemeriksaan | konfirmasi hapus | Terhapus, kembali ke daftar | ☐ |
| B-08 | Hapus balita yang punya pemeriksaan | konfirmasi hapus | Ditolak dengan alasan yang jelas | ☐ |
| B-09 | Pencarian | kata kunci nama | Hanya balita yang cocok tampil | ☐ |
| B-10 | Filter JK/desa/posyandu | pilih filter | Daftar tersaring sesuai pilihan | ☐ |
| B-11 | Pagination | data > 15 baris | Navigasi halaman berfungsi | ☐ |
| B-12 | NIK disamarkan | lihat daftar | NIK tampil sebagai `5203********9999` | ☐ |
| B-13 | Empty state | belum ada data | Pesan "Belum ada data balita" + tombol aksi | ☐ |

### 2.4 Antropometri

| No | Skenario | Masukan | Keluaran yang Diharapkan | Hasil |
|---|---|---|---|---|
| M-01 | Tambah pemeriksaan valid | tanggal, BB, TB | Tersimpan, usia terhitung otomatis | ☐ |
| M-02 | Usia otomatis | lahir 15/01/2024, ukur 01/09/2026 | Usia = 31 bulan | ☐ |
| M-03 | Tanggal sebelum lahir | tanggal < tanggal lahir | Ditolak dengan pesan jelas | ☐ |
| M-04 | Tanggal masa depan | tanggal > hari ini | Ditolak | ☐ |
| M-05 | Berat negatif | `-5` | Ditolak | ☐ |
| M-06 | Berat/tinggi tidak wajar | `250` kg | Pesan "di luar batas wajar" | ☐ |
| M-07 | Isian bukan angka | `sebelas` | Pesan "harus berupa angka" | ☐ |
| M-08 | Tanggal ganda | tanggal yang sudah ada | Pesan "sudah memiliki data pemeriksaan pada tanggal tersebut" | ☐ |
| M-09 | Ubah pemeriksaan | ubah tanggal | Usia dihitung ulang | ☐ |
| M-10 | Hapus pemeriksaan berprediksi | konfirmasi hapus | Ditolak dengan alasan | ☐ |
| M-11 | Grafik pertumbuhan | buka detail balita | Grafik BB & TB tampil berurutan menurut tanggal | ☐ |

### 2.5 Import E-PPBGM

| No | Skenario | Masukan | Keluaran yang Diharapkan | Hasil |
|---|---|---|---|---|
| I-01 | Unggah CSV | berkas ekspor E-PPBGM | Pratinjau kolom dan jumlah baris tampil | ☐ |
| I-02 | Unggah XLSX | berkas Excel | Pratinjau tampil, tanggal Excel terbaca | ☐ |
| I-03 | Berkas terlarang | unggah `.php` | Ditolak dengan pesan format | ☐ |
| I-04 | Berkas terlalu besar | > batas ukuran | Ditolak dengan pesan ukuran | ☐ |
| I-05 | Kolom wajib belum dipetakan | kosongkan pemetaan BB | Pesan "Kolom wajib ... belum dipetakan" | ☐ |
| I-06 | Validasi menghitung benar | berkas dengan 1 baris rusak & 1 duplikat | Ringkasan: total, siap, tidak valid, duplikat | ☐ |
| I-07 | Baris bermasalah dilaporkan | idem | Tabel nomor baris + alasan | ☐ |
| I-08 | Belum commit, belum tersimpan | berhenti sebelum konfirmasi | Basis data belum berubah | ☐ |
| I-09 | Commit | tekan Konfirmasi | Data masuk, ringkasan batch tampil | ☐ |
| I-10 | Tanggal `dd/mm/yyyy` | `15/01/2024` | Terbaca benar | ☐ |
| I-11 | Desimal koma | `11,2` | Terbaca sebagai 11,2 | ☐ |
| I-12 | Jenis kelamin variatif | `Laki-laki`, `P`, `LK` | Dinormalkan ke `L`/`P` | ☐ |
| I-13 | Import ulang berkas sama | commit dua kali | Tidak ada duplikat; dilaporkan sebagai duplikat | ☐ |
| I-14 | Unduh template | tekan Unduh Template | Berkas CSV contoh terunduh | ☐ |

### 2.6 Prediksi

| No | Skenario | Masukan | Keluaran yang Diharapkan | Hasil |
|---|---|---|---|---|
| P-01 | Prediksi sukses | ML API aktif + model siap | Status, probabilitas, versi model tampil | ☐ |
| P-02 | Tombol saat model belum siap | ML API tanpa model | Tombol nonaktif + peringatan | ☐ |
| P-03 | ML API mati | hentikan ML API | "Layanan prediksi sedang tidak tersedia." | ☐ |
| P-04 | Tidak ada hasil palsu | setelah P-03 | Tidak ada baris prediksi baru di basis data | ☐ |
| P-05 | API key salah | ubah `ML_API_KEY` | Pesan penolakan, tidak menyimpan | ☐ |
| P-06 | Model belum tersedia | `MODEL_NOT_READY` | "Model prediksi belum tersedia." | ☐ |
| P-07 | Loading state | tekan Analisis Risiko | Tombol berubah "MENGANALISIS..." dan nonaktif | ☐ |
| P-08 | Cegah klik ganda | klik dua kali cepat | Hanya satu permintaan terkirim | ☐ |
| P-09 | Tanpa identitas ke ML | amati log ML API | Payload hanya 4 fitur + `request_id` | ☐ |
| P-10 | Disclaimer tampil | buka hasil prediksi | Disclaimer dan istilah "Prediksi", bukan "Diagnosis" | ☐ |
| P-11 | Snapshot tidak berubah | ubah pemeriksaan setelah prediksi | Hasil lama tetap menampilkan nilai saat prediksi | ☐ |
| P-12 | Histori tetap terbaca | setelah model baru aktif | Prediksi lama masih menampilkan versi model lamanya | ☐ |

### 2.7 Dashboard, Monitoring, Laporan

| No | Skenario | Masukan | Keluaran yang Diharapkan | Hasil |
|---|---|---|---|---|
| D-01 | Angka dari basis data | tambah data lalu buka dashboard | Kartu statistik berubah sesuai data | ☐ |
| D-02 | Dashboard kosong | basis data kosong | Semua angka 0 dan empty state, tanpa data contoh | ☐ |
| D-03 | Grafik distribusi | ada hasil prediksi | Doughnut chart sesuai label | ☐ |
| D-04 | Tren 12 bulan | data beberapa bulan | Grafik garis 12 titik | ☐ |
| D-05 | Monitoring filter | filter desa/posyandu/hasil | Tabel dan grafik tersaring | ☐ |
| D-06 | Laporan 3 jenis | pilih tiap jenis | Tabel sesuai jenis | ☐ |
| D-07 | Filter laporan | rentang tanggal, JK, wilayah | Hasil tersaring | ☐ |
| D-08 | Ekspor CSV ikut filter | ekspor dengan filter aktif | Isi CSV sama dengan yang tampil | ☐ |
| D-09 | Cetak/PDF | tekan Cetak | Kop instansi, periode, tanda tangan; sidebar tidak tercetak | ☐ |

### 2.8 Admin

| No | Skenario | Masukan | Keluaran yang Diharapkan | Hasil |
|---|---|---|---|---|
| S-01 | Tambah pengguna | data lengkap | Tersimpan, kata sandi ter-hash | ☐ |
| S-02 | Username/email duplikat | data yang sudah ada | Ditolak | ☐ |
| S-03 | Kata sandi lemah | `abc` | Ditolak dengan syarat minimal | ☐ |
| S-04 | Aktif/nonaktif pengguna | tekan tombol status | Status berubah | ☐ |
| S-05 | Nonaktifkan diri sendiri | pada akun sendiri | Ditolak | ☐ |
| S-06 | Turunkan admin terakhir | ubah peran admin satu-satunya | Ditolak | ☐ |
| S-07 | Reset kata sandi | tekan Reset | Kata sandi acak tampil sekali | ☐ |
| S-08 | Sinkronisasi model | ML API aktif | Versi & metrik tercatat | ☐ |
| S-09 | Metrik belum ada | ML API tanpa metrik | Tampil `-`, bukan `0` | ☐ |
| S-10 | Audit log terisi | lakukan beberapa aksi | LOGIN, CREATE, UPDATE, PREDICT, IMPORT tercatat | ☐ |
| S-11 | Filter audit log | filter aksi & pengguna | Hasil tersaring | ☐ |
| S-12 | Pengaturan tersimpan | ubah nama instansi | Tampil di kop laporan | ☐ |
| S-13 | Kunci ML tidak bocor | buka halaman Pengaturan | Hanya status "Terisi/Kosong", nilai tidak tampil | ☐ |

### 2.9 Keamanan & Antarmuka

| No | Skenario | Masukan | Keluaran yang Diharapkan | Hasil |
|---|---|---|---|---|
| K-01 | CSRF | POST tanpa token | Halaman 419 | ☐ |
| K-02 | XSS | nama berisi `<script>alert(1)</script>` | Ditampilkan sebagai teks, skrip tidak berjalan | ☐ |
| K-03 | SQL injection | `' OR 1=1 --` di kolom cari | Diperlakukan sebagai teks pencarian biasa | ☐ |
| K-04 | Error aman | picu kesalahan di production | Tidak ada stack trace/kredensial | ☐ |
| K-05 | Responsif 360 px | perkecil peramban | Sidebar menjadi offcanvas, tabel dapat digeser | ☐ |
| K-06 | Toast notifikasi | simpan data | Toast konfirmasi muncul | ☐ |
| K-07 | Dialog konfirmasi | tekan hapus | Dialog konfirmasi muncul lebih dulu | ☐ |
| K-08 | Status tidak hanya warna | lihat badge hasil | Selalu ada label teks di samping warna | ☐ |

---

## 3. Formulir User Acceptance Test

Diisi oleh Petugas Gizi Puskesmas Lepak setelah mencoba sistem.
**Jangan mengisi atas nama responden.**

**Nama responden:** ………………………………  **Jabatan:** ………………………………
**Tanggal:** ………………  **Versi sistem:** ………………  **Versi model:** ………………

Skala: 1 = Sangat Tidak Setuju · 2 = Tidak Setuju · 3 = Netral · 4 = Setuju · 5 = Sangat Setuju

| No | Aspek yang Dinilai | 1 | 2 | 3 | 4 | 5 |
|---|---|:-:|:-:|:-:|:-:|:-:|
| 1 | Proses login mudah dilakukan | ☐ | ☐ | ☐ | ☐ | ☐ |
| 2 | Menu dan navigasi mudah dipahami | ☐ | ☐ | ☐ | ☐ | ☐ |
| 3 | Input data balita mudah dilakukan | ☐ | ☐ | ☐ | ☐ | ☐ |
| 4 | Input data pemeriksaan mudah dilakukan | ☐ | ☐ | ☐ | ☐ | ☐ |
| 5 | Perhitungan usia otomatis membantu | ☐ | ☐ | ☐ | ☐ | ☐ |
| 6 | Import data E-PPBGM mudah dilakukan | ☐ | ☐ | ☐ | ☐ | ☐ |
| 7 | Menjalankan prediksi mudah dilakukan | ☐ | ☐ | ☐ | ☐ | ☐ |
| 8 | Hasil prediksi mudah dipahami | ☐ | ☐ | ☐ | ☐ | ☐ |
| 9 | Grafik pertumbuhan membantu pemantauan | ☐ | ☐ | ☐ | ☐ | ☐ |
| 10 | Halaman monitoring membantu memetakan balita berisiko | ☐ | ☐ | ☐ | ☐ | ☐ |
| 11 | Laporan sesuai kebutuhan pelaporan | ☐ | ☐ | ☐ | ☐ | ☐ |
| 12 | Tampilan nyaman dibaca | ☐ | ☐ | ☐ | ☐ | ☐ |
| 13 | Sistem dapat dipakai di ponsel/tablet | ☐ | ☐ | ☐ | ☐ | ☐ |
| 14 | Pesan kesalahan mudah dipahami | ☐ | ☐ | ☐ | ☐ | ☐ |
| 15 | Sistem membantu deteksi dini risiko stunting | ☐ | ☐ | ☐ | ☐ | ☐ |
| 16 | Sistem layak digunakan di Puskesmas Lepak | ☐ | ☐ | ☐ | ☐ | ☐ |

**Fitur yang paling membantu:**
…………………………………………………………………………………………

**Kendala yang ditemui:**
…………………………………………………………………………………………

**Saran perbaikan:**
…………………………………………………………………………………………

Tanda tangan responden: ………………………………

---

## 4. Validasi Rekomendasi Intervensi

Mesin rekomendasi baru boleh diaktifkan setelah aturannya divalidasi tenaga gizi
berwenang. Gunakan lembar berikut, dan lampirkan hasilnya pada skripsi.

| Kategori Hasil Prediksi | Rekomendasi yang Diusulkan | Sumber Pedoman | Disetujui | Catatan |
|---|---|---|:-:|---|
| | | | ☐ | |
| | | | ☐ | |

Divalidasi oleh: ……………………………… (nutrisionis)  Tanggal: ………………

Sebelum lembar ini terisi, sistem hanya menampilkan
*"Perlu evaluasi lebih lanjut oleh Petugas Gizi."* dan tidak menyusun anjuran
medis apa pun.
