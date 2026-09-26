# Deployment ML API ke Vercel

Target: `https://ml.<domain-utama>` — layanan Python/Flask yang menjalankan
inferensi model XGBoost melalui artifact ONNX.

---

## 1. Persiapan

Pastikan artifact model sudah ada:

```
ml-api/models/xgboost_model.onnx
ml-api/models/metadata.json
```

Bila belum, latih lebih dulu (lihat [ML_MODEL.md](ML_MODEL.md)). ML API tetap dapat
di-deploy tanpa model — `GET /api/health` akan melaporkan `model_loaded: false` dan
permintaan prediksi dijawab `MODEL_NOT_READY`.

Bangkitkan API key:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Simpan nilainya; dipakai di **kedua** sisi (Vercel dan Hostinger).

---

## 2. Struktur yang Di-deploy

```
ml-api/
├── api/index.py          entry point Flask
├── ml/
│   ├── predictor.py
│   ├── validator.py
│   └── preprocessing.py
├── models/
│   ├── xgboost_model.onnx
│   └── metadata.json
├── requirements.txt      HANYA flask, onnxruntime, numpy
└── vercel.json
```

`training/` dan `tests/` tidak diperlukan saat runtime. Jangan menambahkan pandas,
scikit-learn, Jupyter, TensorFlow, atau PyTorch ke `requirements.txt` — dependensi
training sudah dipisahkan di `training/requirements-training.txt`.

---

## 3. Deploy

### 3.1 Vercel CLI

```bash
npm i -g vercel
cd ml-api
vercel login
vercel            # deployment pratinjau
vercel --prod     # deployment produksi
```

### 3.2 Melalui Git

Hubungkan repositori di dashboard Vercel, lalu atur **Root Directory** ke `ml-api`.
Karena artifact model masuk `.gitignore`, pilih salah satu:

- deploy lewat CLI dari mesin lokal (model ikut terunggah), atau
- buat pengecualian khusus untuk berkas model pada repositori privat, atau
- unduh model saat build dari penyimpanan privat.

Untuk skripsi, cara paling sederhana adalah **deploy lewat CLI**.

---

## 4. Environment Variables

Dashboard Vercel → Project → Settings → Environment Variables:

| Nama | Nilai | Environment |
|---|---|---|
| `ML_API_KEY` | kunci acak ≥ 32 karakter | Production, Preview |
| `APP_ENV` | `production` | Production |
| `MODEL_PATH` | *(kosongkan)* | — |

`MODEL_PATH` hanya diperlukan bila artifact diletakkan di luar `ml-api/models`.

Setelah mengubah environment variable, **deploy ulang** agar nilainya terbaca.

---

## 5. Custom Domain

1. Vercel → Project → Settings → Domains → tambahkan `ml.<domain-utama>`.
2. Di pengelola DNS domain (Hostinger), tambahkan record yang diminta Vercel —
   umumnya `CNAME` `ml` → `cname.vercel-dns.com`.
3. Tunggu propagasi DNS dan penerbitan sertifikat TLS oleh Vercel.

---

## 6. Verifikasi

```bash
BASE="https://ml.<domain-utama>"
KEY="<ML_API_KEY>"

# 1. Health — harus model_loaded: true
curl -s "$BASE/api/health"

# 2. Tanpa API key — harus 401
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/api/v1/predict" \
  -H "Content-Type: application/json" \
  -d '{"request_id":"550e8400-e29b-41d4-a716-446655440000","gender":"L","age_months":30,"weight_kg":11.4,"height_cm":86.5}'

# 3. Payload tidak valid — harus 422
curl -s -X POST "$BASE/api/v1/predict" \
  -H "Content-Type: application/json" -H "X-API-Key: $KEY" \
  -d '{"request_id":"550e8400-e29b-41d4-a716-446655440000","gender":"L","age_months":-3,"weight_kg":11.4,"height_cm":86.5}'

# 4. Prediksi sungguhan — harus 200 dengan probability 0..1
curl -s -X POST "$BASE/api/v1/predict" \
  -H "Content-Type: application/json" -H "X-API-Key: $KEY" \
  -d '{"request_id":"550e8400-e29b-41d4-a716-446655440001","gender":"L","age_months":30,"weight_kg":11.4,"height_cm":86.5}'

# 5. Info model — metrik harus sesuai hasil training
curl -s -H "X-API-Key: $KEY" "$BASE/api/v1/model/info"
```

Lanjutkan hanya bila kelima langkah memberi hasil yang diharapkan.

---

## 7. Menghubungkan dengan Hostinger

Pada `.env` aplikasi web di Hostinger:

```env
ML_API_URL=https://ml.<domain-utama>
ML_API_KEY=<kunci-yang-sama-dengan-vercel>
ML_API_TIMEOUT=10
```

Lalu:

```bash
php artisan config:clear
```

Verifikasi dari aplikasi: **Admin → Model XGBoost**. Status harus "Terhubung" dan
"Model Dimuat: Ya". Tekan **Sinkronisasi** untuk mencatat versi dan metrik model.

---

## 8. Ukuran Bundle

Deployment native dengan `xgboost==2.1.4` telah diukur menghasilkan bundle
969,46 MB dan melewati batas fungsi Vercel 500 MB. Karena itu runtime produksi
menggunakan artifact ONNX; training dan evaluasi tetap memakai XGBoost asli.

Bila deployment gagal karena ukuran, urutan penanganan:

| Langkah | Cara | Catatan |
|---|---|---|
| 1 | Gunakan artifact `xgboost_model.onnx` dan `onnxruntime` | Implementasi aktif pada proyek ini |
| 2 | Pastikan `xgboost` hanya ada di `training/requirements-training.txt` | Mencegah dependency training masuk bundle |
| 3 | Traversal pohon murni NumPy dari `xgboost_model.json` | Nol dependensi berat, tetapi kode inferensi harus diuji sendiri |
| 4 | Pindahkan ML API ke platform lain | Menyimpang dari arsitektur; laporkan dan minta persetujuan lebih dulu |

---

## 9. Pemecahan Masalah

| Gejala | Kemungkinan Penyebab | Tindakan |
|---|---|---|
| `model_loaded: false` | Artifact tidak ikut ter-deploy | Periksa `models/` ikut terunggah; cek log build |
| 503 `SERVER_MISCONFIGURED` | `ML_API_KEY` belum diset di Vercel | Tambahkan lalu deploy ulang |
| 401 dari aplikasi web | Kunci berbeda di kedua sisi | Samakan nilai, jalankan `php artisan config:clear` |
| Timeout dari aplikasi web | Cold start fungsi serverless | Naikkan `ML_API_TIMEOUT`; panggil `/api/health` lebih dulu |
| 500 saat prediksi | Metadata tidak cocok dengan model | Latih ulang agar keduanya sepasang |
| Build gagal karena ukuran | Dependensi berlebih | Lihat §8 |

Log runtime tersedia di dashboard Vercel → Deployments → Functions. Telusuri dengan
`request_id` untuk mencocokkan dengan log Hostinger.
