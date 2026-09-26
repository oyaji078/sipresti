# API Contract — Hostinger → Vercel

Seluruh permintaan berjalan **server-to-server**. Browser tidak pernah memanggil
endpoint ini secara langsung.

Base URL produksi: `https://ml.<domain-utama>`
Base URL lokal: `http://127.0.0.1:5000`

---

## Header

| Header | Wajib | Keterangan |
|---|---|---|
| `Content-Type: application/json` | ✅ | pada permintaan POST |
| `X-API-Key: <secret>` | ✅ | kecuali `GET /api/health`. Dibandingkan *constant-time* di sisi Vercel |
| `User-Agent: SIPRESTI-Hostinger/1.0` | — | memudahkan penelusuran log |

Batas ukuran badan permintaan: **16 KB**. Timeout sisi Hostinger diatur lewat
`ML_API_TIMEOUT` (default 10 detik), dengan connect timeout 5 detik.

---

## `GET /api/health`

Tidak memerlukan API key, agar dapat dipakai sebagai pemeriksaan kesehatan.

**200 OK** — model siap:

```json
{
  "success": true,
  "service": "SIPRESTI ML API",
  "model_loaded": true,
  "model_version": "XGB-1.0.0"
}
```

**503 Service Unavailable** — model belum dimuat: badan respons sama, dengan
`"model_loaded": false` dan `"model_version": null`.

Respons ini **tidak memuat** API key, path internal, maupun detail server.

---

## `GET /api/v1/model/info`

Memerlukan API key. Dipakai halaman **Admin → Model XGBoost → Sinkronisasi**.

**200 OK**

```json
{
  "success": true,
  "data": {
    "model_version": "XGB-1.0.0",
    "algorithm": "XGBoost",
    "features": ["gender", "age_months", "weight_kg", "height_cm"],
    "feature_order": ["gender", "age_months", "weight_kg", "height_cm"],
    "label_mapping": { "0": "BERISIKO", "1": "TIDAK_BERISIKO" },
    "gender_encoding": { "L": 0, "P": 1 },
    "trained_at": "2026-09-26T10:41:12+00:00",
    "checksum": "sha256:…",
    "dataset_size": 1500,
    "training_size": 1200,
    "testing_size": 300,
    "parameters": { "max_depth": 4, "n_estimators": 300, "…": "…" },
    "metrics": {
      "accuracy": 0.7433,
      "precision": 0.785,
      "recall": 0.822,
      "f1_score": 0.8031
    }
  }
}
```

Nilai `metrics` boleh `null` bila model belum dilatih. Aplikasi web menampilkannya
sebagai `-`, **bukan** `0`.

---

## `POST /api/v1/predict`

### Permintaan

```json
{
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "gender": "L",
  "age_months": 30,
  "weight_kg": 11.4,
  "height_cm": 86.5
}
```

| Field | Tipe | Aturan |
|---|---|---|
| `request_id` | string | Wajib, UUID valid. Dibuat di Hostinger sebelum permintaan dikirim |
| `gender` | string \| number | Wajib. Dipetakan ke numerik memakai `gender_encoding` pada metadata model |
| `age_months` | integer | Wajib, bilangan bulat, 0–60 |
| `weight_kg` | number | Wajib, 0,5–60. Menerima koma desimal (`"11,4"`) |
| `height_cm` | number | Wajib, 20–150. Menerima koma desimal |

**Dilarang dikirim:** `name`, `nik`, `mother_name`, `address`, `village`,
`posyandu`, nomor telepon, `toddler_id`, `code`. Larangan ini diuji otomatis pada
`web-app/tests/Feature/PredictionTest.php`.

### Respons sukses — 200

```json
{
  "success": true,
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "data": {
    "prediction_class": 0,
    "prediction_label": "BERISIKO",
    "probability": 0.922109,
    "model_version": "XGB-1.0.0"
  }
}
```

`probability` berasal dari `predict_proba()` model dan selalu berada pada rentang
0–1. Hostinger menolak respons yang melanggar rentang ini.

### Respons gagal

```json
{
  "success": false,
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "error": { "code": "VALIDATION_ERROR", "message": "…" }
}
```

---

## Kode Error

| HTTP | `error.code` | Penyebab | Perlakuan Hostinger |
|---|---|---|---|
| 400 | `BAD_REQUEST` | Badan bukan JSON valid | Log, pesan umum, tidak menyimpan |
| 401 | `UNAUTHORIZED` | `X-API-Key` salah/kosong | Alarm keamanan, tanpa retry |
| 404 | `NOT_FOUND` | Endpoint tidak ada | Log |
| 405 | `METHOD_NOT_ALLOWED` | Metode HTTP salah | Log |
| 413 | `PAYLOAD_TOO_LARGE` | Melewati 16 KB | Log |
| 422 | `VALIDATION_ERROR` | Tipe salah, NaN/Inf, negatif, di luar rentang | Pesan validasi ke pengguna |
| 429 | `RATE_LIMITED` | Melewati batas laju | Pesan "coba kembali" |
| 500 | `INTERNAL_ERROR` | Kesalahan tak terduga | Pesan umum; detail hanya ke log server |
| 503 | `MODEL_NOT_READY` | Artifact model belum dimuat | "Model prediksi belum tersedia." |
| 503 | `SERVER_MISCONFIGURED` | `ML_API_KEY` belum diset di Vercel | Pesan ke administrator |
| — | *timeout* | Melewati `ML_API_TIMEOUT` | "Layanan prediksi sedang tidak tersedia. Silakan coba kembali." |

**Pada seluruh kondisi gagal, tidak ada baris prediksi yang disimpan.** Sistem
mencatat `PREDICT_FAILED` pada audit log dan menampilkan pesan apa adanya.

---

## Idempotency

- `request_id` UUID dibuat di Hostinger.
- Kolom `predictions.request_id` memiliki batasan **UNIQUE**.
- Bila retry jaringan membuat respons sukses tiba dua kali, penyimpanan kedua
  ditolak oleh basis data dan ditangani sebagai "sudah tersimpan".
- `request_id` yang sama dicatat pada log Hostinger **dan** Vercel sehingga satu
  transaksi dapat ditelusuri lintas server.

---

## Alur Lengkap Prediksi

1. Pengguna login dan membuka data balita.
2. Pengguna memilih data pemeriksaan dan menekan **ANALISIS RISIKO**.
3. Hostinger memverifikasi sesi dan peran (`PETUGAS_GIZI`).
4. Hostinger mengambil `gender`, `age_months`, `weight_kg`, `height_cm`.
5. Hostinger membuat `request_id` UUID.
6. Hostinger mengirim `POST /api/v1/predict` dengan API key dan timeout aktif.
7. Vercel memverifikasi API key secara constant-time.
8. Vercel memvalidasi payload.
9. Vercel melakukan preprocessing sesuai `metadata.json`.
10. ONNX Runtime menjalankan artifact hasil training XGBoost dan menghasilkan
    kelas serta probabilitas.
11. Vercel mengembalikan JSON.
12. Hostinger memvalidasi struktur respons dan rentang probabilitas.
13. Hostinger menyimpan prediksi + `input_snapshot_json` dan menulis audit log
    dalam satu transaksi.
14. Hasil ditampilkan kepada pengguna beserta disclaimer.

Bila langkah 6–12 gagal, alur berhenti di langkah 13 tanpa menulis apa pun.

---

## Contoh `curl`

```bash
KEY="<ML_API_KEY>"
BASE="https://ml.<domain-utama>"

curl -s "$BASE/api/health"

curl -s -X POST "$BASE/api/v1/predict" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $KEY" \
  -d '{"request_id":"550e8400-e29b-41d4-a716-446655440000",
       "gender":"L","age_months":30,"weight_kg":11.4,"height_cm":86.5}'
```

---

## Versi

Perubahan yang bersifat *breaking* memakai prefiks path baru (`/api/v2/`).
Perubahan daftar atau urutan fitur dihitung sebagai **versi model baru**, bukan
perubahan API.
