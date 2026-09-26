# Diagram Sistem

Semua diagram ditulis dengan Mermaid agar dapat diregenerate dan tetap sejalan
dengan kode. Bila alur pada kode berubah, perbarui diagram di berkas ini.

---

## 1. Use Case Diagram

```mermaid
flowchart LR
    ADM(["Admin"])
    PTG(["Petugas Gizi"])

    subgraph SIPRESTI["SIPRESTI"]
        UC01(["Login"])
        UC02(["Logout"])
        UC03(["Lihat Dashboard"])
        UC04(["Kelola Data Balita"])
        UC05(["Lihat Data Balita"])
        UC06(["Kelola Data Antropometri"])
        UC07(["Import Data E-PPBGM"])
        UC08(["Jalankan Prediksi Risiko"])
        UC09(["Lihat Histori Prediksi"])
        UC10(["Monitoring & Visualisasi"])
        UC11(["Kelola Laporan"])
        UC12(["Kelola Pengguna"])
        UC13(["Lihat Informasi & Metrics Model"])
        UC14(["Lihat Audit Log"])
        UC15(["Kelola Pengaturan"])
        UC16(["Kelola Profil Sendiri"])
    end

    ML(["ML API XGBoost"])

    PTG --- UC01
    PTG --- UC02
    PTG --- UC03
    PTG --- UC04
    PTG --- UC06
    PTG --- UC07
    PTG --- UC08
    PTG --- UC09
    PTG --- UC10
    PTG --- UC11
    PTG --- UC16

    ADM --- UC01
    ADM --- UC02
    ADM --- UC03
    ADM --- UC05
    ADM --- UC09
    ADM --- UC12
    ADM --- UC13
    ADM --- UC14
    ADM --- UC15
    ADM --- UC16

    UC08 --- ML
    UC13 --- ML
```

### Identifikasi Aktor

| No | Aktor | Deskripsi |
|---|---|---|
| 1 | **Admin** | Memelihara sistem: pengguna, pengaturan, audit log, serta memantau informasi dan metrik model. Tidak memiliki hak tulis atas data klinis. |
| 2 | **Petugas Gizi** | Mengelola data balita dan antropometri dari E-PPBGM, menjalankan prediksi, memantau pertumbuhan, dan membuat laporan. |

### Identifikasi Use Case

| No | Use Case | Aktor | Rute |
|---|---|---|---|
| 1 | Login | Admin, Petugas Gizi | `GET/POST /login` |
| 2 | Logout | Admin, Petugas Gizi | `POST /logout` |
| 3 | Lihat Dashboard | Admin, Petugas Gizi | `GET /dashboard` |
| 4 | Kelola Data Balita | Petugas Gizi | `/balita` (CRUD) |
| 5 | Lihat Data Balita | Admin | `GET /balita`, `GET /balita/{id}` |
| 6 | Kelola Data Antropometri | Petugas Gizi | `/pemeriksaan` (CRUD) |
| 7 | Import Data E-PPBGM | Petugas Gizi | `/import` |
| 8 | Jalankan Prediksi Risiko | Petugas Gizi | `POST /prediksi` |
| 9 | Lihat Histori Prediksi | Admin, Petugas Gizi | `GET /prediksi` |
| 10 | Monitoring & Visualisasi | Petugas Gizi | `GET /monitoring` |
| 11 | Kelola Laporan | Petugas Gizi | `/laporan`, `/laporan/cetak`, `/laporan/ekspor` |
| 12 | Kelola Pengguna | Admin | `/admin/users` |
| 13 | Lihat Informasi & Metrics Model | Admin | `/admin/models`, `/admin/model-metrics` |
| 14 | Lihat Audit Log | Admin | `/admin/audit-logs` |
| 15 | Kelola Pengaturan | Admin | `/admin/settings` |
| 16 | Kelola Profil Sendiri | Admin, Petugas Gizi | `/profil` |

---

## 2. Activity Diagram — Login

```mermaid
flowchart TD
    A([Mulai]) --> B[Buka halaman login]
    B --> C[Isi username/email dan kata sandi]
    C --> D{Isian lengkap?}
    D -- Tidak --> E[Tampilkan pesan wajib diisi] --> C
    D -- Ya --> F{Melebihi 5 percobaan gagal?}
    F -- Ya --> G[Tampilkan pesan batas percobaan] --> Z([Selesai])
    F -- Tidak --> H{Kredensial cocok?}
    H -- Tidak --> I[Catat LOGIN_FAILED] --> J[Tampilkan pesan gagal] --> C
    H -- Ya --> K{Status akun aktif?}
    K -- Tidak --> L[Keluarkan dari sesi] --> M[Pesan akun nonaktif] --> C
    K -- Ya --> N[Regenerasi session id]
    N --> O[Simpan last_login_at]
    O --> P[Catat LOGIN pada audit log]
    P --> Q{Peran}
    Q -- ADMIN --> R[Dashboard admin]
    Q -- PETUGAS_GIZI --> S[Dashboard petugas]
    R --> Z
    S --> Z
```

---

## 3. Activity Diagram — Kelola Data Balita

```mermaid
flowchart TD
    A([Mulai]) --> B[Buka menu Data Balita]
    B --> C{Aksi}

    C -- Cari/Filter --> D[Terapkan pencarian & filter] --> E[Tampilkan daftar terpaginasi] --> C

    C -- Tambah --> F[Buka form tambah]
    F --> G[Isi identitas balita]
    G --> H{Validasi lolos?}
    H -- Tidak --> I[Tampilkan pesan per kolom] --> G
    H -- Ya --> J[Bangkitkan kode BLT-xxxxxx]
    J --> K[Simpan + catat audit CREATE] --> L[Buka halaman detail] --> Z([Selesai])

    C -- Ubah --> M[Buka form ubah] --> N[Perbarui data]
    N --> O{Validasi lolos?}
    O -- Tidak --> P[Tampilkan pesan] --> N
    O -- Ya --> Q[Simpan + catat audit UPDATE] --> Z

    C -- Hapus --> R[Tampilkan dialog konfirmasi]
    R --> S{Dikonfirmasi?}
    S -- Tidak --> C
    S -- Ya --> T{Punya data pemeriksaan?}
    T -- Ya --> U[Tolak + jelaskan alasan] --> C
    T -- Tidak --> V[Hapus + catat audit DELETE] --> Z

    C -- Detail --> W[Tampilkan identitas, pemeriksaan terakhir,<br/>grafik pertumbuhan, histori pemeriksaan & prediksi] --> Z
```

---

## 4. Activity Diagram — Input Pemeriksaan

```mermaid
flowchart TD
    A([Mulai]) --> B[Buka form pemeriksaan]
    B --> C[Pilih balita]
    C --> D[Isi tanggal pemeriksaan]
    D --> E[Sistem menghitung usia dari tanggal lahir<br/>dan tanggal pemeriksaan]
    E --> F[Isi berat badan dan tinggi/panjang badan]
    F --> G{Tanggal <= hari ini?}
    G -- Tidak --> H[Tolak: tanggal masa depan] --> D
    G -- Ya --> I{Tanggal >= tanggal lahir?}
    I -- Tidak --> J[Tolak: mendahului tanggal lahir] --> D
    I -- Ya --> K{Usia <= batas 60 bulan?}
    K -- Tidak --> L[Tolak: melebihi batas usia] --> D
    K -- Ya --> M{BB & TB numerik dan dalam batas wajar?}
    M -- Tidak --> N[Tolak dengan pesan pada kolom terkait] --> F
    M -- Ya --> O{Sudah ada pemeriksaan pada tanggal itu?}
    O -- Ya --> P[Tolak: duplikat tanggal] --> D
    O -- Tidak --> Q[Simpan age_months sebagai snapshot]
    Q --> R[source = MANUAL, catat audit CREATE]
    R --> S[Tampilkan detail pemeriksaan] --> Z([Selesai])
```

---

## 5. Activity Diagram — Prediksi Risiko

```mermaid
flowchart TD
    A([Mulai]) --> B[Buka halaman Prediksi]
    B --> C[Sistem memeriksa kesehatan ML API]
    C --> D{Layanan siap & model dimuat?}
    D -- Tidak --> E[Tampilkan peringatan,<br/>tombol dinonaktifkan] --> Z([Selesai])
    D -- Ya --> F[Pilih balita]
    F --> G[Pilih data pemeriksaan]
    G --> H[Tampilkan identitas & data pengukuran]
    H --> I[Tekan ANALISIS RISIKO]
    I --> J[Tombol dinonaktifkan, tampil MENGANALISIS...]
    J --> K[Verifikasi peran PETUGAS_GIZI]
    K --> L[Ambil 4 fitur + buat request_id UUID]
    L --> M[Kirim ke ML API dengan API key & timeout]
    M --> N{Respons sukses & valid?}
    N -- Tidak --> O[Catat PREDICT_FAILED]
    O --> P[Tampilkan pesan layanan tidak tersedia<br/>TANPA menyimpan hasil] --> Z
    N -- Ya --> Q{Probability 0..1?}
    Q -- Tidak --> O
    Q -- Ya --> R[Buka transaksi]
    R --> S[Simpan prediksi + input_snapshot_json]
    S --> T[Catat audit PREDICT]
    T --> U[Commit transaksi]
    U --> V[Tampilkan hasil + disclaimer] --> Z
```

---

## 6. Activity Diagram — Import E-PPBGM

```mermaid
flowchart TD
    A([Mulai]) --> B[Buka menu Import E-PPBGM]
    B --> C[Unggah berkas CSV/XLSX]
    C --> D{Ekstensi, MIME, ukuran valid?}
    D -- Tidak --> E[Tolak dengan pesan format/ukuran] --> C
    D -- Ya --> F[Simpan dengan nama UUID di storage privat]
    F --> G[Baca header & 10 baris pertama]
    G --> H[Tampilkan pratinjau]
    H --> I[Petakan kolom sumber ke kolom sistem]
    I --> J{Kolom wajib terpetakan?}
    J -- Tidak --> K[Tolak: sebut kolom yang belum dipetakan] --> I
    J -- Ya --> L[Validasi seluruh baris]
    L --> M[Tampilkan ringkasan:<br/>total, siap, tidak valid, duplikat]
    M --> N[Tampilkan daftar baris bermasalah + alasan]
    N --> O{Ada baris valid?}
    O -- Tidak --> P[Tombol impor dinonaktifkan] --> Z([Selesai])
    O -- Ya --> Q[Tekan Konfirmasi & Impor]
    Q --> R[Buka transaksi basis data]
    R --> S[Cocokkan balita berdasarkan NIK<br/>atau nama + tanggal lahir]
    S --> T{Balita sudah ada?}
    T -- Ya --> U[Pakai data yang ada]
    T -- Tidak --> V[Buat balita baru dengan kode BLT-xxxxxx]
    U --> W{Pemeriksaan tanggal itu sudah ada?}
    V --> W
    W -- Ya --> X[Lewati sebagai duplikat]
    W -- Tidak --> Y[Simpan pemeriksaan, source = IMPORT_EPPBGM]
    X --> AA[Perbarui ringkasan batch]
    Y --> AA
    AA --> AB[Catat audit IMPORT, commit transaksi]
    AB --> AC[Hapus berkas sementara]
    AC --> AD[Tampilkan ringkasan hasil] --> Z
```

---

## 7. Sequence Diagram — Prediksi

```mermaid
sequenceDiagram
    autonumber
    actor PG as Petugas Gizi
    participant BR as Browser
    participant HG as Hostinger (Laravel)
    participant DB as MySQL
    participant ML as Vercel (Flask)
    participant XG as XGBoost

    PG->>BR: Pilih pemeriksaan, tekan ANALISIS RISIKO
    BR->>HG: POST /prediksi (measurement_id) + CSRF
    HG->>HG: Verifikasi sesi & peran PETUGAS_GIZI
    HG->>DB: SELECT measurement + toddler.birth_date
    DB-->>HG: gender, age_months, weight_kg, height_cm
    HG->>HG: Buat request_id (UUID), susun payload 4 fitur
    HG->>ML: POST /api/v1/predict + X-API-Key
    ML->>ML: Verifikasi API key (constant-time)
    ML->>ML: Validasi payload
    ML->>ML: Preprocessing sesuai metadata.json
    ML->>XG: predict() dan predict_proba()
    XG-->>ML: kelas + probabilitas
    ML-->>HG: JSON {success, data{...}}
    HG->>HG: Validasi struktur & rentang probabilitas

    alt Respons valid
        HG->>DB: BEGIN
        HG->>DB: INSERT prediction + input_snapshot_json
        HG->>DB: INSERT audit_log (PREDICT)
        HG->>DB: COMMIT
        HG-->>BR: Redirect ke halaman hasil
        BR-->>PG: Status, probabilitas, versi model, disclaimer
    else Gagal / timeout / model belum siap
        HG->>DB: INSERT audit_log (PREDICT_FAILED)
        HG-->>BR: Pesan layanan tidak tersedia
        Note over HG,DB: Tidak ada baris prediksi yang disimpan
        BR-->>PG: Pesan kegagalan yang jelas
    end
```

---

## 8. Architecture Diagram

```mermaid
flowchart TB
    U["Petugas Gizi / Admin<br/>(Browser)"]

    subgraph HOST["HOSTINGER — https://&lt;domain-utama&gt;"]
        WEB["Laravel — MVC + Service Layer"]
        subgraph MOD["Modul"]
            direction LR
            M1["Auth"]
            M2["Dashboard"]
            M3["Toddler"]
            M4["Measurement"]
            M5["Import"]
            M6["Prediction"]
            M7["Monitoring"]
            M8["Reporting"]
            M9["User Mgmt"]
            M10["Audit"]
        end
        SVC["MlPredictionService<br/>payload · API key · timeout · parse · log"]
        DB[("MySQL")]
    end

    subgraph VERC["VERCEL — https://ml.&lt;domain-utama&gt;"]
        API["Flask — api/index.py"]
        VAL["validator.py"]
        PRE["preprocessing.py"]
        XGB["XGBoost<br/>xgboost_model.json + metadata.json"]
    end

    U -->|HTTPS| WEB
    WEB --- MOD
    MOD --> SVC
    WEB <--> DB
    SVC -->|"HTTPS + X-API-Key<br/>4 fitur, tanpa identitas"| API
    API --> VAL --> PRE --> XGB
    XGB -->|JSON| SVC
    SVC --> DB
    U -. "DILARANG: akses langsung" .-x API
```

---

## 9. Deployment Diagram

```mermaid
flowchart TB
    subgraph CLIENT["Perangkat Pengguna"]
        BR["Peramban<br/>Chrome / ponsel / tablet"]
    end

    subgraph DNS["DNS"]
        D1["&lt;domain-utama&gt; → A record"]
        D2["ml.&lt;domain-utama&gt; → CNAME Vercel"]
    end

    subgraph HOSTINGER["Hostinger — Shared Hosting"]
        PHP["PHP 8.2+ / Apache<br/>document root → public/"]
        APP["Aplikasi Laravel"]
        MYSQL[("MySQL 127.0.0.1:3306")]
        CRON["Cron — cadangan basis data harian"]
    end

    subgraph VERCEL["Vercel — Serverless"]
        FUNC["Python Function<br/>Flask + XGBoost"]
        ENV["Environment Variables<br/>ML_API_KEY"]
    end

    BR -->|HTTPS 443| D1 --> PHP --> APP
    APP <--> MYSQL
    CRON --> MYSQL
    APP -->|HTTPS 443 + API key| D2 --> FUNC
    FUNC --- ENV
```

---

## 10. Class Diagram (Model Domain)

```mermaid
classDiagram
    class User {
        +int id
        +string name
        +string username
        +string email
        +UserRole role
        +UserStatus status
        +datetime last_login_at
        +isAdmin() bool
        +isPetugasGizi() bool
        +isActive() bool
    }

    class Toddler {
        +int id
        +string code
        +string nik
        +string name
        +date birth_date
        +string gender
        +currentAgeMonths() int
        +ageLabel() string
        +maskedNik() string
        +nextCode() string
    }

    class Measurement {
        +int id
        +date measurement_date
        +int age_months
        +float weight_kg
        +float height_cm
        +MeasurementSource source
        +calculateAgeMonths(birth, at) int
        +hasPrediction() bool
    }

    class Prediction {
        +int id
        +string prediction_class
        +string prediction_label
        +float probability
        +array input_snapshot_json
        +string request_id
        +datetime predicted_at
        +isAtRisk() bool
        +statusLabel() string
        +probabilityPercent() string
    }

    class ModelVersion {
        +int id
        +string version
        +string algorithm
        +float accuracy
        +float precision_score
        +float recall
        +float f1_score
        +bool is_active
        +hasMetrics() bool
        +metricLabel(v) string
    }

    class ImportHistory {
        +int id
        +string original_name
        +int total_rows
        +int success_rows
        +int invalid_rows
        +int duplicate_rows
        +string status
    }

    class AuditLog {
        +int id
        +string action
        +string entity
        +int entity_id
        +string description
        +string ip_address
    }

    class MlPredictionService {
        +isConfigured() bool
        +buildFeatures(Measurement) array
        +predict(features, requestId) array
        +health() array
        +modelInfo() array
    }

    class PredictionService {
        +run(Measurement, User) Prediction
    }

    User "1" --> "0..*" Toddler : created_by
    User "1" --> "0..*" Measurement : created_by
    User "1" --> "0..*" Prediction : predicted_by
    User "1" --> "0..*" AuditLog
    Toddler "1" --> "0..*" Measurement
    Toddler "1" --> "0..*" Prediction
    Measurement "1" --> "0..*" Prediction
    ModelVersion "1" --> "0..*" Prediction
    ImportHistory "1" --> "0..*" Measurement
    PredictionService --> MlPredictionService
    PredictionService --> Prediction
```
