# Instalasi Lokal

Panduan rinci untuk menjalankan SIPRESTI di mesin pengembangan.

---

## 1. Kebutuhan

| Komponen | Versi Minimal | Pemeriksaan |
|---|---|---|
| PHP | 8.2 | `php -v` |
| Composer | 2.x | `composer -V` |
| MySQL / MariaDB | MySQL 5.7 / MariaDB 10.3 | `mysql --version` |
| Python | 3.10 | `python --version` |
| Git | 2.x | `git --version` |

Ekstensi PHP yang diperlukan: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`,
`tokenizer`, `xml`, `ctype`, `json`, `zip`, `curl`.

Periksa dengan:

```bash
php -m
```

Bila ada yang kurang pada instalasi PHP mandiri (bukan XAMPP/Laragon), aktifkan di
`php.ini`:

```ini
extension_dir = "ext"
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=fileinfo
extension=zip
extension=curl
extension=intl
```

Node.js **tidak diperlukan**. Bootstrap 5, Bootstrap Icons, dan Chart.js sudah
tersimpan lokal di `web-app/public/vendor/`, sehingga aplikasi tetap berjalan tanpa
koneksi internet — penting untuk demo di puskesmas.

---

## 2. Basis Data

```sql
CREATE DATABASE sipresti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE sipresti_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'sipresti'@'127.0.0.1' IDENTIFIED BY 'kata-sandi-anda';
GRANT ALL PRIVILEGES ON sipresti.* TO 'sipresti'@'127.0.0.1';
GRANT ALL PRIVILEGES ON sipresti_testing.* TO 'sipresti'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Basis data `sipresti_testing` dipakai oleh pengujian otomatis dan akan dikosongkan
berulang kali — jangan menyimpan data penting di sana.

---

## 3. Aplikasi Web

```bash
cd web-app
composer install
cp .env.example .env
php artisan key:generate
```

Sunting `.env` pada bagian basis data dan ML API:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sipresti
DB_USERNAME=sipresti
DB_PASSWORD=kata-sandi-anda

ML_API_URL=http://127.0.0.1:5000
ML_API_KEY=kunci-pengembangan-minimal-32-karakter
ML_API_TIMEOUT=10
```

Jalankan migrasi:

```bash
php artisan migrate
```

Buat akun administrator:

```bash
php artisan sipresti:create-admin
```

Kata sandi acak akan ditampilkan satu kali. Untuk menentukan sendiri:

```bash
php artisan sipresti:create-admin --username=admin --password=rahasia123 --force
```

Jalankan server:

```bash
php artisan serve
```

Buka `http://127.0.0.1:8000`.

### Data contoh untuk pengembangan

```bash
php artisan migrate:fresh --seed
```

Menghasilkan 12 balita dan 36 pemeriksaan **sintetis** (alamat ditandai
`DATA SINTETIS - PENGEMBANGAN`), serta dua akun:

| Peran | Username | Kata sandi |
|---|---|---|
| ADMIN | `admin` | dari `SEED_PASSWORD` di `.env`, default `sipresti2026` |
| PETUGAS_GIZI | `petugas` | sama |

Seeder **tidak** menanam hasil prediksi — prediksi hanya boleh berasal dari model
sungguhan. Seeder menolak berjalan bila `APP_ENV=production`.

---

## 4. ML API

```bash
cd ml-api
python -m venv .venv
```

Aktifkan lalu pasang dependensi:

```bash
# Windows
.venv\Scripts\python.exe -m pip install -r requirements.txt
# Linux / macOS
.venv/bin/python -m pip install -r requirements.txt
```

```bash
cp .env.example .env
```

Isi `ML_API_KEY` dengan nilai **yang sama** seperti di `web-app/.env`.

Jalankan:

```bash
# Windows (PowerShell)
$env:ML_API_KEY="kunci-pengembangan-minimal-32-karakter"; .venv\Scripts\python.exe api\index.py

# Windows (cmd)
set ML_API_KEY=kunci-pengembangan-minimal-32-karakter && .venv\Scripts\python.exe api\index.py

# Linux / macOS
ML_API_KEY=kunci-pengembangan-minimal-32-karakter .venv/bin/python api/index.py
```

Uji:

```bash
curl http://127.0.0.1:5000/api/health
```

Tanpa artifact model, respons `model_loaded: false` dengan HTTP 503 adalah **perilaku
yang benar**, bukan kegagalan instalasi.

---

## 5. Model untuk Pengembangan

Agar jalur prediksi dapat diuji sebelum dataset asli tersedia:

```bash
cd ml-api
.venv/Scripts/python.exe -m pip install -r training/requirements-training.txt

.venv/Scripts/python.exe training/make_dev_dataset.py --rows 1500 \
    --out training/data/dev-synthetic.csv

.venv/Scripts/python.exe training/train.py \
    --data training/data/dev-synthetic.csv \
    --col-gender jenis_kelamin --col-age usia_bulan \
    --col-weight berat_kg --col-height tinggi_cm \
    --col-label status_gizi --col-id id_balita \
    --version XGB-0.0.1-DEV-SYNTHETIC \
    --note "Model pengembangan dari data SINTETIS. Bukan hasil penelitian."
```

Jalankan ulang ML API agar artifact terbaca, lalu periksa
`GET /api/health` menampilkan `model_loaded: true`.

Model ini **tidak boleh** dipakai sebagai hasil penelitian. Untuk model
sesungguhnya, lihat [ML_MODEL.md](ML_MODEL.md).

---

## 6. Menjalankan Pengujian

```bash
cd web-app && php artisan test

cd ml-api && ML_API_KEY=kunci-uji-untuk-pengujian-otomatis .venv/Scripts/python.exe -m pytest tests -q
```

---

## 7. Urutan Menjalankan Sehari-hari

1. Jalankan MySQL.
2. Jalankan ML API di port 5000.
3. Jalankan `php artisan serve` di port 8000.
4. Buka `http://127.0.0.1:8000`.

---

## 8. Pemecahan Masalah

| Gejala | Penyebab | Tindakan |
|---|---|---|
| `could not find driver` | `pdo_mysql` belum aktif | Aktifkan di `php.ini`, lalu cek `php -m` |
| `Connection refused` ke MySQL | Server MySQL belum jalan | Jalankan MySQL/MariaDB |
| `No application encryption key` | `APP_KEY` kosong | `php artisan key:generate` |
| Perubahan `.env` tidak terbaca | Konfigurasi ter-cache | `php artisan config:clear` |
| Tampilan berubah setelah edit Blade | Tampilan ter-cache | `php artisan view:clear` |
| `SQLSTATE[42S02]` tabel tidak ada | Migrasi belum jalan | `php artisan migrate` |
| Pengujian gagal di basis data | `sipresti_testing` belum ada | Buat basis data uji |
| `ModuleNotFoundError: flask` | venv belum aktif | Jalankan lewat `.venv/Scripts/python.exe` |
| ML API 503 `SERVER_MISCONFIGURED` | `ML_API_KEY` belum diset | Set environment variable sebelum menjalankan |
| Prediksi 401 dari aplikasi web | Kunci berbeda di kedua sisi | Samakan, lalu `php artisan config:clear` |
| Import CSV ditolak | Pastikan ekstensi `.csv`/`.xlsx` | Pesan validasi menjelaskan alasannya |
