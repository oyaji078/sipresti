# SIPRESTI — Sistem Prediksi Risiko Stunting Balita

Sistem pendukung keputusan berbasis web untuk membantu **Petugas Gizi Puskesmas Lepak**
melakukan deteksi dini risiko stunting balita menggunakan algoritma **XGBoost**
berdasarkan data antropometri E-PPBGM.

> **Sistem ini adalah alat bantu pengambilan keputusan (Decision Support System),
> bukan sistem diagnosis medis otomatis.** Setiap hasil prediksi ditampilkan sebagai
> "Prediksi Risiko Berdasarkan Model" beserta disclaimer, dan tidak menggantikan
> pemeriksaan maupun penilaian tenaga kesehatan.

---

## 1. Arsitektur

Arsitektur **hybrid**: aplikasi web di Hostinger, layanan machine learning di Vercel.

```
Browser (Petugas Gizi / Admin)
   |  HTTPS
   v
HOSTINGER — Aplikasi Web (PHP 8.2+ / Laravel, MVC + Service Layer)
   |-- Auth, Dashboard, Balita, Pemeriksaan, Import,
   |   Prediksi, Monitoring, Laporan, User Mgmt, Audit
   |
   |--> MySQL (Hostinger)
   |
   |  HTTPS + X-API-Key   (hanya 4 fitur, tanpa identitas)
   v
VERCEL — ML API (Python / Flask)
   Validation -> Preprocessing -> XGBoost -> predict_proba
   |
   v  JSON
HOSTINGER -> simpan ke MySQL -> tampilkan ke Browser
```

**Browser tidak pernah memanggil ML API secara langsung.** Seluruh permintaan
prediksi berjalan server-to-server sehingga API key tidak terekspos, validasi
dilakukan di sisi server, dan seluruh aktivitas tercatat di satu tempat.

### Pembagian tanggung jawab

| | Hostinger | Vercel |
|---|---|---|
| Autentikasi & otorisasi | ✅ | ❌ |
| Data balita & identitas | ✅ | ❌ **tidak pernah dikirim** |
| Penyimpanan hasil prediksi | ✅ | ❌ (stateless) |
| Preprocessing & inferensi XGBoost | ❌ | ✅ |
| Audit log | ✅ | ❌ |

---

## 2. Struktur Repositori

```
posyandu_xgboost/
├── web-app/            Aplikasi Laravel (Hostinger)
│   ├── app/
│   │   ├── Enums/                UserRole, UserStatus, MeasurementSource
│   │   ├── Http/Controllers/     web + area admin
│   │   ├── Http/Requests/        Form Request (validasi)
│   │   ├── Http/Middleware/      EnsureUserHasRole, EnsureUserIsActive
│   │   ├── Models/               User, Toddler, Measurement, Prediction, ...
│   │   ├── Services/             MlPredictionService, PredictionService, ...
│   │   └── Console/Commands/     sipresti:create-admin
│   ├── database/migrations/      8 tabel
│   ├── resources/views/          Blade + Bootstrap 5
│   ├── public/{css,js,vendor}/   aset lokal (tanpa CDN)
│   ├── lang/id/                  pesan validasi bahasa Indonesia
│   └── tests/                    115 test
│
├── ml-api/             ML API Python (Vercel)
│   ├── api/index.py              Flask app
│   ├── ml/                       predictor, validator, preprocessing
│   ├── models/                   artifact model (tidak di-commit)
│   ├── training/                 pipeline training offline
│   └── tests/                    40 test
│
└── docs/               Dokumentasi teknis
```

---

## 3. Kebutuhan

| Komponen | Versi |
|---|---|
| PHP | 8.2 atau lebih baru |
| Composer | 2.x |
| MySQL / MariaDB | MySQL 5.7+ atau MariaDB 10.3+ |
| Python | 3.10 atau lebih baru |

Tidak memerlukan Node.js: Bootstrap, Bootstrap Icons, dan Chart.js sudah tersimpan
lokal di `web-app/public/vendor/`, sehingga aplikasi tetap berjalan tanpa internet.

---

## 4. Instalasi Lokal

### 4.1 Aplikasi Web

```bash
cd web-app
composer install
cp .env.example .env
php artisan key:generate
```

Sunting `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sipresti
DB_USERNAME=sipresti
DB_PASSWORD=<kata-sandi-anda>

ML_API_URL=http://127.0.0.1:5000
ML_API_KEY=<kunci-acak-minimal-32-karakter>
ML_API_TIMEOUT=10
```

Buat basis data lalu jalankan migrasi:

```sql
CREATE DATABASE sipresti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate
php artisan sipresti:create-admin      # kata sandi acak, ditampilkan sekali
php artisan serve
```

Aplikasi berjalan di `http://127.0.0.1:8000`.

**Data contoh untuk pengembangan** (opsional, menolak berjalan di production):

```bash
php artisan migrate:fresh --seed
```

Seeder menanam **data sintetis** yang ditandai jelas, membuat akun `admin` dan
`petugas`, dan **tidak** menanam hasil prediksi apa pun — prediksi hanya boleh
berasal dari model sungguhan.

### 4.2 ML API

```bash
cd ml-api
python -m venv .venv
.venv/Scripts/python.exe -m pip install -r requirements.txt   # Linux/macOS: .venv/bin/python
cp .env.example .env        # isi ML_API_KEY, harus sama dengan di web-app
```

Jalankan:

```bash
# Windows
set ML_API_KEY=<kunci-yang-sama> && .venv\Scripts\python api\index.py
# Linux/macOS
ML_API_KEY=<kunci-yang-sama> .venv/bin/python api/index.py
```

ML API berjalan di `http://127.0.0.1:5000`.

Tanpa artifact model, `GET /api/health` mengembalikan `model_loaded: false` dan
setiap permintaan prediksi dijawab `MODEL_NOT_READY`. Aplikasi web menanganinya
dengan pesan yang jelas dan **tidak** menyimpan hasil palsu.

---

## 5. Melatih Model

Training dijalankan **offline**, terpisah dari runtime aplikasi.

```bash
cd ml-api
.venv/Scripts/python.exe -m pip install -r training/requirements-training.txt

.venv/Scripts/python.exe training/train.py \
    --data training/data/eppbgm.xlsx \
    --col-gender "Jenis Kelamin" \
    --col-age "Usia (bulan)" \
    --col-weight "Berat Badan" \
    --col-height "Tinggi Badan" \
    --col-label "Status TB/U" \
    --col-id "NIK" \
    --version XGB-1.0.0
```

Skrip ini:

1. Tidak mengasumsikan nama kolom — semuanya diberikan lewat argumen.
2. Mengambil **mapping label apa adanya** dari dataset, tidak menebak `0`/`1`.
3. Menjalankan **pemeriksaan data leakage** dan berhenti bila label ternyata
   merupakan fungsi deterministik dari fitur antropometri (gunakan
   `--allow-leakage` hanya bila memang disengaja).
4. Melakukan split **per balita** bila `--col-id` diberikan, agar pengukuran anak
   yang sama tidak bocor antara data latih dan data uji.
5. **Tidak** menyeimbangkan kelas secara otomatis; gunakan
   `--balance scale_pos_weight` bila memang diperlukan.
6. Melaporkan Accuracy, Precision, Recall, F1-score, Confusion Matrix, ROC-AUC.
7. Menulis `models/xgboost_model.json` dan `models/metadata.json` —
   seluruh metrik berasal dari evaluasi nyata.

Setelah model tersedia: deploy ML API, lalu buka **Admin → Model XGBoost →
Sinkronisasi** untuk mencatat versi dan metriknya ke basis data.

Detail lengkap: [docs/ML_MODEL.md](docs/ML_MODEL.md).

---

## 6. Peran Pengguna

| Kapabilitas | ADMIN | PETUGAS_GIZI |
|---|:---:|:---:|
| Login / Logout, Dashboard, Profil | ✅ | ✅ |
| Lihat balita, pemeriksaan, hasil prediksi | ✅ | ✅ |
| CRUD balita & pemeriksaan | ❌ | ✅ |
| Import E-PPBGM | ❌ | ✅ |
| Menjalankan prediksi | ❌ | ✅ |
| Monitoring, Laporan | ❌ | ✅ |
| Kelola pengguna, pengaturan, audit log | ✅ | ❌ |
| Lihat informasi & metrics model | ✅ | ❌ |

Admin sengaja tidak memiliki hak tulis atas data klinis: pengelola sistem bukan
pengelola data pasien. Otorisasi ditegakkan oleh middleware di sisi server,
bukan dengan menyembunyikan menu.

---

## 7. Pengujian

```bash
# Aplikasi web (115 test)
cd web-app && php artisan test

# ML API (40 test)
cd ml-api && ML_API_KEY=kunci-uji-untuk-pengujian-otomatis .venv/Scripts/python.exe -m pytest tests -q
```

Pengujian web memakai basis data MySQL terpisah (`sipresti_testing`), bukan SQLite,
agar sama dengan skema produksi. Buat lebih dulu:

```sql
CREATE DATABASE sipresti_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Matriks Black Box Testing dan formulir UAT: [docs/TESTING.md](docs/TESTING.md).

---

## 8. Deployment

- Hostinger (aplikasi web + MySQL): [docs/DEPLOYMENT_HOSTINGER.md](docs/DEPLOYMENT_HOSTINGER.md)
- Vercel (ML API): [docs/DEPLOYMENT_VERCEL.md](docs/DEPLOYMENT_VERCEL.md)

---

## 9. Dokumentasi

| Berkas | Isi |
|---|---|
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Instalasi rinci dan pemecahan masalah |
| [docs/API.md](docs/API.md) | Kontrak API Hostinger ↔ Vercel |
| [docs/DATABASE.md](docs/DATABASE.md) | Skema, relasi, indeks, ERD |
| [docs/ML_MODEL.md](docs/ML_MODEL.md) | Pipeline training, artifact, reproducibility |
| [docs/TESTING.md](docs/TESTING.md) | Matriks Black Box, UAT, ML validation |
| [docs/SECURITY.md](docs/SECURITY.md) | Kontrol keamanan dan privasi |
| [docs/UML.md](docs/UML.md) | Use case, activity, sequence, deployment diagram |
| [docs/PHASE-0-AUDIT.md](docs/PHASE-0-AUDIT.md) | Audit requirement & arsitektur awal |

---

## 10. Catatan Penting

- **Tidak ada data atau metrik yang dikarang.** Bila model belum tersedia, sistem
  menampilkan status sebenarnya. Bila layanan prediksi gagal, tidak ada hasil yang
  disimpan.
- **Data pasien nyata tidak boleh masuk repositori.** Gunakan data sintetis yang
  ditandai jelas untuk pengembangan.
- **Basis data produksi memakai MySQL**, sedangkan draf awal penelitian menyebut
  SQLite. Penyesuaian ini beserta lokasi perubahannya di naskah dicatat pada
  [docs/PHASE-0-AUDIT.md](docs/PHASE-0-AUDIT.md) §14.
