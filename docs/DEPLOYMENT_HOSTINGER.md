# Deployment Aplikasi Web ke Hostinger

Target: `https://<domain-utama>` — aplikasi Laravel dengan basis data MySQL.

---

## 1. Verifikasi Lingkungan Terlebih Dahulu

Lakukan pemeriksaan ini **sebelum** mengunggah apa pun. Hasilnya menentukan apakah
Laravel dapat dipakai langsung.

| Yang diperiksa | Cara | Syarat |
|---|---|---|
| Versi PHP | hPanel → Advanced → PHP Configuration | 8.2 atau lebih baru |
| Ekstensi PHP | PHP Configuration → PHP Extensions | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `json`, `zip` |
| Composer | SSH: `composer -V` | Bila tidak ada, unggah folder `vendor/` dari lokal |
| Akses SSH | hPanel → Advanced → SSH Access | Untuk menjalankan `php artisan` |
| Document root | hPanel → Websites → domain | Harus dapat diarahkan ke `public/` |
| `upload_max_filesize` | PHP Configuration | ≥ 12 MB agar import E-PPBGM aman |
| `max_execution_time` | PHP Configuration | ≥ 120 detik |

Bila SSH atau Composer tidak tersedia, jalankan `composer install --no-dev` di mesin
lokal lalu unggah termasuk `vendor/`. Bila Laravel benar-benar tidak dapat berjalan,
struktur basis data dan kontrak API **tidak boleh diubah** — hanya kerangka aplikasi
yang diganti.

---

## 2. Menyiapkan Basis Data

hPanel → Databases → MySQL Databases:

1. Buat basis data, mis. `uXXXXXX_sipresti`.
2. Buat pengguna dengan kata sandi kuat, beri hak penuh pada basis data tersebut.
3. Catat nama basis data, pengguna, dan kata sandinya.

Dari aplikasi yang sudah ter-deploy, sambungkan ke **`127.0.0.1` port `3306`**
(MySQL berada di server yang sama). Host `srvNNNN.hstgr.io` hanya untuk koneksi dari
luar Hostinger dan memerlukan Remote MySQL diaktifkan lebih dulu.

---

## 3. Mengunggah Aplikasi

### 3.1 Lewat Git (disarankan bila SSH tersedia)

```bash
ssh uXXXXXXX@<host>
cd ~/domains/<domain-utama>
git clone <url-repo> app
cd app/web-app
composer install --no-dev --optimize-autoloader
```

### 3.2 Lewat unggahan arsip

Di mesin lokal:

```bash
cd web-app
composer install --no-dev --optimize-autoloader
```

Kemas seluruh isi `web-app/` **termasuk `vendor/`**, kecualikan `.env`,
`node_modules/`, `storage/logs/*`, dan `.git/`. Unggah lalu ekstrak lewat File
Manager.

---

## 4. Mengatur Document Root

Laravel menyajikan aplikasi dari `public/`, bukan dari akar proyek.

**Cara yang disarankan:** hPanel → Websites → domain → Advanced → ubah document root
menjadi `.../app/web-app/public`.

**Bila document root tidak dapat diubah:** letakkan isi `public/` di
`public_html/`, sisanya di luar `public_html/`, lalu sesuaikan `public_html/index.php`:

```php
require __DIR__.'/../app/web-app/vendor/autoload.php';
$app = require_once __DIR__.'/../app/web-app/bootstrap/app.php';
```

Jangan pernah membiarkan `.env`, `storage/`, atau `vendor/` dapat diakses lewat URL.

---

## 5. Konfigurasi `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Isi:

```env
APP_NAME=SIPRESTI
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<domain-utama>
APP_LOCALE=id
APP_TIMEZONE=Asia/Makassar

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=uXXXXXX_sipresti
DB_USERNAME=uXXXXXX_sipresti
DB_PASSWORD=<kata-sandi-basis-data>

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

ML_API_URL=https://ml.<domain-utama>
ML_API_KEY=<kunci-yang-sama-dengan-vercel>
ML_API_TIMEOUT=10

APP_INSTANSI="Puskesmas Lepak"
```

`APP_DEBUG=false` wajib di production.

---

## 6. Migrasi dan Akun Admin

```bash
php artisan migrate --force
php artisan sipresti:create-admin
```

Perintah kedua menampilkan kata sandi acak **satu kali**. Catat, lalu segera ganti
setelah login pertama.

**Jangan** menjalankan `php artisan db:seed` di production — seeder berisi data
sintetis dan akan menolak berjalan bila `APP_ENV=production`.

---

## 7. Izin Folder dan Optimasi

```bash
chmod -R 775 storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Bila `.env` diubah kemudian, jalankan `php artisan config:clear` lalu
`php artisan config:cache` lagi.

---

## 8. SSL dan HTTPS

1. hPanel → Websites → domain → Security → SSL → pasang sertifikat gratis.
2. Aktifkan **Force HTTPS**.
3. Pastikan `APP_URL` memakai `https://` dan `SESSION_SECURE_COOKIE=true`.

`AppServiceProvider` sudah memaksa skema HTTPS saat `APP_ENV=production`.

---

## 9. Cadangan Basis Data

Lewat SSH:

```bash
mysqldump -u <user> -p <database> > ~/backup/sipresti-$(date +%F).sql
```

Jadwalkan lewat hPanel → Advanced → Cron Jobs, mis. harian pukul 01.00:

```
0 1 * * * mysqldump -u <user> -p'<password>' <database> | gzip > ~/backup/sipresti-$(date +\%F).sql.gz
```

Hostinger juga menyediakan backup otomatis pada beberapa paket — periksa
ketersediaannya di hPanel → Files → Backups.

---

## 10. Verifikasi Setelah Deploy

```
[ ] https://<domain-utama> membuka halaman login
[ ] Login administrator berhasil
[ ] Dashboard terbuka, angka sesuai basis data (kosong bila belum ada data)
[ ] Tambah balita berhasil, kode BLT-000001 terbentuk
[ ] Tambah pemeriksaan berhasil, usia terhitung otomatis
[ ] Import E-PPBGM: unggah, pemetaan, validasi, commit berjalan
[ ] Admin → Model XGBoost menampilkan "Terhubung" dan "Model Dimuat: Ya"
[ ] Sinkronisasi model mencatat versi dan metrik
[ ] Prediksi end-to-end berhasil, probabilitas tampil
[ ] Hentikan ML API sebentar: muncul pesan layanan tidak tersedia, tanpa data palsu
[ ] Audit log terisi
[ ] Petugas Gizi tidak dapat membuka /admin/users (403)
[ ] Admin tidak dapat membuka /balita-tambah (403)
[ ] Laporan dapat dicetak dan diekspor sesuai filter
[ ] Tampilan rapi di ponsel
[ ] Halaman error tidak menampilkan stack trace
```

---

## 11. Pemecahan Masalah

| Gejala | Penyebab | Tindakan |
|---|---|---|
| 500 tanpa pesan | Izin `storage/` | `chmod -R 775 storage bootstrap/cache` |
| "No application encryption key" | `APP_KEY` kosong | `php artisan key:generate` |
| Halaman blank | `APP_DEBUG=false` menyembunyikan error | Baca `storage/logs/laravel.log` |
| Akses ke `/` menampilkan daftar berkas | Document root salah | Arahkan ke `public/` |
| CSS/JS tidak muncul | `APP_URL` salah | Perbaiki lalu `php artisan config:cache` |
| Koneksi basis data gagal | Memakai host `srvNNNN.hstgr.io` | Gunakan `127.0.0.1` |
| Sesi hilang berulang | `SESSION_SECURE_COOKIE=true` tanpa HTTPS | Aktifkan SSL |
| Import gagal untuk berkas besar | `upload_max_filesize` kecil | Naikkan di PHP Configuration |
| Prediksi selalu gagal | `ML_API_URL`/`ML_API_KEY` salah | Cek Admin → Pengaturan, samakan dengan Vercel |
