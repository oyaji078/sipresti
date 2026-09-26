# Model Machine Learning

Algoritma: **XGBoost** (eXtreme Gradient Boosting).
Fitur: `gender`, `age_months`, `weight_kg`, `height_cm`.
Target: status stunting / tidak stunting, **mengikuti label pada dataset asli**.

---

## 1. Pemisahan Training dan Inference

```
OFFLINE (laptop peneliti)                RUNTIME (Vercel)
─────────────────────────                ─────────────────
dataset E-PPBGM                          permintaan dari Hostinger
    ↓ validation                             ↓ validasi API key
    ↓ cleaning                               ↓ validasi payload
    ↓ feature preparation                    ↓ load model (sekali saat init)
    ↓ leakage check                          ↓ preprocessing (metadata.json)
    ↓ train/test split                       ↓ predict()
    ↓ XGBoost training                       ↓ predict_proba()
    ↓ evaluation                             ↓ respons JSON
    ↓ save artifact
xgboost_model.json + metadata.json
```

Training **tidak pernah** dijalankan saat pengguna menekan tombol prediksi.
Model dimuat satu kali pada inisialisasi modul, bukan per permintaan.

---

## 2. Menjalankan Training

```bash
cd ml-api
.venv/Scripts/python.exe -m pip install -r training/requirements-training.txt

.venv/Scripts/python.exe training/train.py \
    --data training/data/eppbgm.xlsx \
    --col-gender "Jenis Kelamin" \
    --col-age    "Usia (bulan)" \
    --col-weight "Berat Badan" \
    --col-height "Tinggi Badan" \
    --col-label  "Status TB/U" \
    --col-id     "NIK" \
    --version    XGB-1.0.0
```

### Argumen penting

| Argumen | Fungsi |
|---|---|
| `--col-*` | Nama kolom pada dataset. **Wajib** — struktur dataset tidak diasumsikan |
| `--col-id` | Kolom pengenal balita. Bila diisi, split dilakukan **per balita** |
| `--version` | Versi model, mis. `XGB-1.0.0` |
| `--test-size` | Proporsi data uji (default 0,2) |
| `--random-state` | Seed (default 42), dicatat ke metadata |
| `--balance` | `none` (default) atau `scale_pos_weight` |
| `--allow-leakage` | Lanjut walau terdeteksi indikasi leakage |
| `--out` | Folder keluaran (default `ml-api/models`) |

Jalankan `python training/train.py --help` untuk daftar lengkap.

---

## 3. Tahapan yang Dijalankan Skrip

### 3.1 Validation

Memuat berkas (CSV dengan deteksi pemisah `;`/`,`, atau Excel), melaporkan dimensi
dan sidik SHA-256 berkas. Bila kolom yang diminta tidak ada, skrip mencetak daftar
kolom yang tersedia lalu berhenti.

### 3.2 Cleaning

- Angka diparsing dengan toleransi koma desimal.
- Baris dengan nilai kosong pada kolom wajib dibuang.
- Nilai non-positif pada berat/tinggi dibuang.
- Duplikat baris identik dibuang.
- Jumlah setiap kategori dilaporkan, tidak disembunyikan.

Ambang klinis **tidak dikarang**. Yang diterapkan hanya penyaringan nilai mustahil.

### 3.3 Feature Preparation

`gender_encoding` dan `label_mapping` dibangun dari **nilai yang benar-benar ada di
dataset**, diurutkan alfabetis, lalu dicatat ke `metadata.json`. Tidak ada asumsi
bahwa `0 = tidak stunting` atau `1 = stunting`.

Contoh keluaran:

```
gender_encoding:  'L' -> 0   'P' -> 1
label_mapping:    0 -> 'BERISIKO' (n=549)   1 -> 'TIDAK_BERISIKO' (n=951)
```

### 3.4 Leakage Check

Ini pemeriksaan yang paling penting untuk dibaca.

Pada E-PPBGM, status stunting dihitung dari **Z-score TB/U**, yang merupakan fungsi
dari jenis kelamin + usia + tinggi badan — tiga dari empat fitur model. Bila label
dataset memang berasal dari perhitungan tersebut, model tidak memprediksi risiko,
melainkan **menghafal ulang tabel standar antropometri**. Gejalanya: akurasi
99–100% dan F1 mendekati 1,0.

Skrip melatih pohon keputusan dangkal pada `(gender, age_months, height_cm)` saja
dan mengukur akurasinya pada data latihnya sendiri. Bila ≥ 0,995, training
**dihentikan** dengan penjelasan dan saran:

- memprediksi status pada pengukuran **berikutnya** (butuh data longitudinal), atau
- menambah prediktor non-antropometrik (BBLR, ASI eksklusif, imunisasi, dan
  sebagainya), atau
- membingkai penelitian sebagai otomatisasi klasifikasi, dengan baseline pembanding.

Gunakan `--allow-leakage` hanya bila keputusan itu diambil secara sadar. Hasil
pemeriksaan selalu ikut disimpan ke `metadata.json` pada bagian `leakage_check`.

### 3.5 Split

Bila `--col-id` diberikan, split memakai `GroupShuffleSplit` sehingga **seluruh
pengukuran satu balita berada di sisi yang sama**. Tanpa ini, satu anak dengan tiga
kali penimbangan bisa muncul di data latih *dan* data uji, sehingga metrik menjadi
terlalu optimistis.

### 3.6 Imbalance

Distribusi kelas dan rasio terbanyak:terkecil dilaporkan lebih dulu.
**Tidak ada penyeimbangan otomatis.** `scale_pos_weight` hanya diterapkan bila
diminta eksplisit, dan nilainya dicatat ke metadata.

### 3.7 Evaluation

Dilaporkan: **Accuracy, Precision, Recall, F1-score, Confusion Matrix**, ROC-AUC,
classification report per kelas, serta feature importance.

Untuk skrining kesehatan, **recall pada kelas berisiko lebih penting daripada
precision** — melewatkan balita berisiko lebih berbahaya daripada memunculkan
alarm palsu yang nantinya diperiksa petugas.

### 3.8 Save Artifact

| Berkas | Isi |
|---|---|
| `models/xgboost_model.json` | Model format native XGBoost |
| `models/metadata.json` | Encoding, mapping, parameter, metrik, checksum |

Contoh struktur metadata ada di `models/metadata.example.json` (seluruh nilainya
placeholder `null`).

---

## 4. Reproducibility

`metadata.json` menyimpan:

- `random_state`
- `features` dan `feature_order`
- `gender_encoding` dan `label_mapping`
- `library_versions` (python, xgboost, numpy, pandas)
- `parameters` XGBoost lengkap
- `split` (ukuran dataset/latih/uji, `grouped_by`)
- `dataset_fingerprint` (SHA-256 berkas sumber) dan `source_columns`
- `trained_at`
- `checksum` artifact model
- `leakage_check`
- `observed_ranges` (informatif, bukan gerbang validasi)

Dependensi training dipisahkan pada `training/requirements-training.txt` supaya
paket berat seperti pandas dan scikit-learn **tidak** ikut ter-deploy ke Vercel.

---

## 5. Deploy dan Registrasi Versi

1. Latih model, hasilkan `xgboost_model.json` + `metadata.json`.
2. Letakkan keduanya di `ml-api/models/`.
3. Deploy `ml-api` ke Vercel (lihat [DEPLOYMENT_VERCEL.md](DEPLOYMENT_VERCEL.md)).
4. Pastikan `GET /api/health` mengembalikan `model_loaded: true`.
5. Di aplikasi web: **Admin → Model XGBoost → Sinkronisasi**.

Sinkronisasi mengambil `GET /api/v1/model/info` dan mencatat versi beserta
metriknya ke tabel `model_versions`. Bila ML API tidak mengirim metrik, kolomnya
tetap `NULL` dan antarmuka menampilkan `-`.

**Hanya satu model aktif pada satu waktu.** Artifact model diperbarui melalui
proses deployment, bukan melalui unggah dari halaman web — filesystem Vercel
bersifat read-only saat runtime.

---

## 6. Data Sintetis untuk Pengembangan

Sebelum dataset asli tersedia, jalur integrasi dapat diuji dengan data buatan:

```bash
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

Aturan yang wajib dipatuhi:

- Versi model **harus** berakhiran `-DEV-SYNTHETIC`.
- Model ini **tidak boleh** dipakai sebagai hasil penelitian atau masuk BAB IV.
- Berkas dataset sintetis tidak di-commit (`training/data/` masuk `.gitignore`).

Generator sengaja memakai faktor laten yang tidak menjadi fitur model, sehingga
label tidak menjadi fungsi deterministik dari antropometri dan pemeriksaan leakage
tetap bermakna saat dijalankan.

---

## 7. Pengujian Model

`ml-api/tests/test_api.py` memeriksa:

| Aspek | Kriteria |
|---|---|
| Model dapat dimuat | Tanpa exception saat inisialisasi |
| Jumlah & nama fitur | Sesuai `metadata.features` |
| Urutan fitur | Diuji eksplisit — salah urutan menyebabkan prediksi salah diam-diam |
| Preprocessing konsisten | Input sama menghasilkan keluaran sama |
| Label valid | Ada di dalam `label_mapping` |
| Probability | Berada pada rentang 0–1 |
| Fitur hilang | Ditolak `422` |
| Angka tidak valid | NaN/Inf/negatif/teks ditolak `422` |
| Versi model tersedia | `/api/health` mengembalikan versi |
| Keluaran benar-benar dari fitur | Dua kondisi berbeda harus memberi probabilitas berbeda |

```bash
cd ml-api
ML_API_KEY=kunci-uji-untuk-pengujian-otomatis .venv/Scripts/python.exe -m pytest tests -q
```

---

## 8. Yang Dilarang

- Menulis metrik ke `metadata.json`, `model_versions`, dokumentasi, atau BAB IV
  tanpa training sebenarnya.
- Mengembalikan probabilitas yang tidak berasal dari `predict_proba()`.
- Menyimpan hasil prediksi ketika ML API gagal.
- Menebak `label_mapping` atau `gender_encoding` sebelum dataset diperiksa.
- Menimpa model tanpa menaikkan versi.
