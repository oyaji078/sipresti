# Keamanan dan Privasi

Aplikasi ini mengelola data kesehatan balita. Dokumen ini mencatat kontrol yang
sudah diterapkan, cara memeriksanya, dan hal yang sengaja **tidak** dilakukan.

---

## 1. Lapisan Pertahanan

| # | Lapisan | Kontrol |
|---|---|---|
| 1 | Transport | HTTPS; di production `URL::forceScheme('https')` aktif |
| 2 | Sesi | Cookie `Secure` + `HttpOnly` + `SameSite`; `session()->regenerate()` saat login; `invalidate()` + `regenerateToken()` saat logout |
| 3 | Autentikasi | Kata sandi di-hash (bcrypt), rate limit login, status akun diperiksa tiap permintaan |
| 4 | Otorisasi | Middleware `role:` per rute + `Form Request::authorize()` per aksi |
| 5 | Masukan | Form Request untuk semua penulisan, CSRF pada semua POST/PUT/PATCH/DELETE, Eloquent (prepared statement) |
| 6 | Keluaran | Blade `{{ }}` meng-escape otomatis; tidak ada `{!! !!}` pada data pengguna |
| 7 | Unggahan | Ekstensi, MIME, ukuran, jumlah baris dibatasi; nama berkas diganti UUID; disimpan di luar document root |
| 8 | Rahasia | Hanya dari environment variable; tidak pernah di Git, HTML, atau JavaScript |
| 9 | Batas ML | API key wajib, perbandingan constant-time, timeout, batas ukuran badan permintaan |
| 10 | Privasi | Data minimization; identitas tidak pernah dikirim ke Vercel; NIK disamarkan |
| 11 | Observabilitas | Audit log otomatis; `request_id` menghubungkan log Hostinger ↔ Vercel |

---

## 2. Autentikasi dan Otorisasi

### Kata sandi

- Di-hash memakai bcrypt lewat cast `'password' => 'hashed'` pada model `User`.
  Tidak pernah ada kata sandi polos di basis data maupun log.
- Syarat minimal: 8 karakter, mengandung huruf dan angka
  (`Password::min(8)->letters()->numbers()`).
- Kata sandi awal administrator **tidak** ditulis di dalam kode. Perintah
  `php artisan sipresti:create-admin` membangkitkan kata sandi acak dan
  menampilkannya satu kali.
- Reset kata sandi oleh admin juga membangkitkan nilai acak, bukan nilai tetap.

### Rate limiting

- `POST /login` dibatasi `throttle:10,1` pada level rute.
- `LoginRequest` membatasi 5 percobaan gagal per kombinasi username + IP.
- `POST /prediksi` dibatasi `throttle:30,1`.

### Peran

Dua peran: `ADMIN` dan `PETUGAS_GIZI`. Penegakan dilakukan di **dua** tempat:

1. `EnsureUserHasRole` pada definisi rute.
2. `authorize()` pada Form Request untuk setiap aksi penulisan.

Menyembunyikan menu di sidebar **tidak** dihitung sebagai otorisasi. 28 test pada
`RolePermissionTest` memanggil rute secara langsung untuk membuktikannya.

### Akun nonaktif

`EnsureUserIsActive` memeriksa status pada setiap permintaan. Akun yang
dinonaktifkan langsung dikeluarkan dari sesi meski sesinya masih hidup.

### Perlindungan administrator terakhir

Sistem menolak menurunkan peran atau menonaktifkan satu-satunya administrator
aktif, dan menolak pengguna menonaktifkan akunnya sendiri.

---

## 3. Privasi Data

### Data minimization pada panggilan ML

Hanya empat nilai yang dikirim ke Vercel:

```json
{ "request_id": "<uuid>", "gender": "L", "age_months": 30,
  "weight_kg": 11.4, "height_cm": 86.5 }
```

**Tidak pernah dikirim:** nama, NIK, nama ibu, alamat, desa, posyandu, kode balita,
maupun `toddler_id`. Larangan ini diuji otomatis pada
`web-app/tests/Feature/PredictionTest.php::test_payload_ke_ml_api_hanya_memuat_empat_fitur_tanpa_identitas`,
yang memeriksa daftar kunci payload **dan** memastikan tidak ada potongan identitas
di dalam badan permintaan.

### Penyamaran NIK

`Toddler::maskedNik()` menampilkan `5203********9999`. Dipakai di daftar balita dan
laporan. NIK utuh hanya tampil pada form ubah data bagi Petugas Gizi.

### Yang tidak di-log

Kata sandi, API key, dan token sesi tidak pernah masuk log aplikasi.
`MlPredictionService` mencatat `request_id`, status, dan durasi — bukan isi payload.
`AuditService` memotong deskripsi pada 255 karakter dan hanya mencatat kode balita,
bukan data antropometri lengkap.

### Data pasien dan repositori

`.gitignore` memblokir `*.csv`, `*.xlsx`, `*.sql`, `ml-api/training/data/`, dan
folder unggahan. Untuk pengembangan gunakan data sintetis dari
`training/make_dev_dataset.py` atau seeder, yang keduanya menandai dirinya sebagai
data sintetis.

### Penyimpanan berkas import

Berkas diunggah ke `storage/app/private/imports/` dengan nama UUID, di luar
document root sehingga tidak dapat diakses lewat URL maupun dieksekusi. Berkas
dihapus setelah proses import selesai.

---

## 4. Keamanan Batas ML API

| Kontrol | Implementasi |
|---|---|
| API key wajib | Header `X-API-Key` pada `/api/v1/*` |
| Perbandingan aman | `hmac.compare_digest()` — bukan `==` |
| Konfigurasi hilang | Mengembalikan `SERVER_MISCONFIGURED` 503, tidak melewatkan permintaan |
| Batas ukuran | `MAX_CONTENT_LENGTH` 16 KB |
| Timeout | `ML_API_TIMEOUT` di sisi Hostinger (default 10 detik, connect 5 detik) |
| Tanpa kebocoran | `/api/health` hanya memuat 4 field; tidak ada path atau secret |
| Stateless | Vercel tidak menyimpan permintaan maupun respons |

Browser **tidak** memiliki jalur apa pun ke ML API: tidak ada endpoint proxy publik,
dan API key hanya berada di server.

---

## 5. Penanganan Kesalahan

Di production (`APP_DEBUG=false`) pengguna tidak melihat stack trace, kredensial
basis data, API key, path absolut, maupun detail error SQL.

Pesan kegagalan prediksi yang ditampilkan:

| Kondisi | Pesan |
|---|---|
| ML API tidak terhubung / timeout | "Layanan prediksi sedang tidak tersedia. Silakan coba kembali." |
| Model belum ada | "Model prediksi belum tersedia." |
| API key ditolak | "Layanan prediksi menolak permintaan. Hubungi administrator." |
| Belum dikonfigurasi | "Layanan prediksi belum dikonfigurasi. Hubungi administrator." |

Dalam setiap kondisi gagal, **tidak ada hasil prediksi yang disimpan** dan
`PREDICT_FAILED` dicatat pada audit log. Detail teknis hanya masuk log server.

---

## 6. Audit Log

Aksi yang dicatat otomatis: `LOGIN`, `LOGIN_FAILED`, `LOGOUT`, `CREATE`, `UPDATE`,
`DELETE`, `IMPORT`, `PREDICT`, `PREDICT_FAILED`, `USER_CREATE`, `USER_UPDATE`,
`SETTING_UPDATE`, `MODEL_SYNC`.

Setiap baris memuat pengguna, aksi, entitas, id entitas, deskripsi ringkas, alamat
IP, dan user agent. Kegagalan penulisan audit tidak menggagalkan aksi utama
pengguna, tetapi dicatat sebagai error aplikasi.

---

## 7. Keamanan Medis

- Sistem adalah **Decision Support System**, bukan alat diagnosis.
- Istilah yang dipakai: "Prediksi Risiko Berdasarkan Model", "Probabilitas keluaran
  model", "Perlu evaluasi lebih lanjut oleh Petugas Gizi".
- Kata "Diagnosis" tidak dipakai; ada test yang memastikan kata itu tidak muncul di
  halaman hasil.
- Disclaimer wajib pada setiap hasil prediksi dan pada laporan prediksi tercetak.
- Warna tidak pernah menjadi satu-satunya pembawa informasi status — selalu ada
  label teks.
- Sistem **tidak** menyusun rekomendasi klinis, dosis, maupun terapi. Mesin
  rekomendasi baru aktif setelah aturannya divalidasi tenaga gizi berwenang.

---

## 8. Pengelolaan Rahasia

| Lokasi | Variabel |
|---|---|
| Hostinger `.env` | `APP_KEY`, `DB_*`, `ML_API_URL`, `ML_API_KEY`, `ML_API_TIMEOUT` |
| Vercel Environment Variables | `ML_API_KEY`, `MODEL_PATH`, `APP_ENV` |
| Repositori | `.env.example` saja, tanpa nilai nyata |

`ML_API_KEY` harus identik di kedua sisi. Bangkitkan dengan:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Halaman **Admin → Pengaturan** hanya menampilkan status `Terisi`/`Kosong`; nilai URL
dan kunci tidak pernah dikirim ke peramban. Kredensial juga tidak dapat diubah dari
antarmuka — hanya lewat environment variable.

---

## 9. Daftar Periksa Sebelum Production

```
[ ] APP_DEBUG=false dan APP_ENV=production
[ ] APP_KEY sudah di-generate
[ ] HTTPS aktif, HTTP dialihkan
[ ] SESSION_SECURE_COOKIE=true
[ ] ML_API_KEY acak minimal 32 karakter, sama di Hostinger dan Vercel
[ ] .env tidak ada di Git (cek: git log --all -- .env)
[ ] Kata sandi admin bawaan sudah diganti
[ ] Tidak ada data pasien nyata di repositori
[ ] Cadangan basis data terjadwal
[ ] php artisan test lulus
[ ] pytest ML API lulus
[ ] GET /api/health mengembalikan model_loaded: true
[ ] Prediksi end-to-end berhasil
[ ] Audit log terisi
[ ] Uji otorisasi lintas peran lulus
[ ] Halaman error production tidak membocorkan detail
```

---

## 10. Pertimbangan Legal

Data antropometri balita beserta identitasnya termasuk data pribadi yang bersifat
spesifik menurut UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.
Konsekuensinya:

- Diperlukan surat izin penelitian atau persetujuan tertulis dari Puskesmas Lepak
  sebelum data diekspor dari E-PPBGM.
- Data yang dikirim ke layanan pihak ketiga tidak boleh mengandung identitas
  (sudah diterapkan, lihat §3).
- Untuk demonstrasi dan sidang, gunakan data yang sudah dianonimkan.

---

## 11. Melaporkan Masalah Keamanan

Temuan keamanan dilaporkan langsung kepada pengembang dan administrator Puskesmas,
bukan melalui kanal publik. Sertakan langkah reproduksi dan dampaknya, tanpa
melampirkan data pasien nyata.
