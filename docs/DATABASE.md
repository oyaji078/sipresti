# Skema Basis Data

MySQL / MariaDB, `utf8mb4_unicode_ci`. Seluruh tabel dibuat lewat **migration**
Laravel (`web-app/database/migrations/`), tidak pernah manual.

---

## ERD

```mermaid
erDiagram
    users ||--o{ toddlers : "created_by"
    users ||--o{ measurements : "created_by"
    users ||--o{ predictions : "predicted_by"
    users ||--o{ import_histories : "user_id"
    users ||--o{ audit_logs : "user_id"
    users ||--o{ settings : "updated_by"

    toddlers ||--o{ measurements : "memiliki"
    toddlers ||--o{ predictions : "memiliki"
    measurements ||--o{ predictions : "menjadi dasar"
    model_versions ||--o{ predictions : "menghasilkan"
    import_histories ||--o{ measurements : "sumber IMPORT_EPPBGM"

    users {
        bigint id PK
        varchar name
        varchar username UK
        varchar email UK
        varchar password "hashed"
        enum role "ADMIN | PETUGAS_GIZI"
        enum status "ACTIVE | INACTIVE"
        timestamp last_login_at
    }

    toddlers {
        bigint id PK
        varchar code UK "BLT-000001"
        varchar nik "nullable, unik bila ada"
        varchar name
        date birth_date
        enum gender "L | P"
        varchar mother_name
        varchar address
        varchar village
        varchar posyandu
        bigint created_by FK
    }

    measurements {
        bigint id PK
        bigint toddler_id FK
        date measurement_date
        smallint age_months "snapshot historis"
        decimal weight_kg "5,2"
        decimal height_cm "5,1"
        enum source "MANUAL | IMPORT_EPPBGM"
        bigint import_history_id FK "nullable"
        bigint created_by FK
    }

    predictions {
        bigint id PK
        bigint toddler_id FK
        bigint measurement_id FK
        bigint model_version_id FK "nullable"
        varchar prediction_class
        varchar prediction_label
        decimal probability "8,6"
        json input_snapshot_json
        char request_id UK "UUID 36"
        bigint predicted_by FK
        timestamp predicted_at
    }

    model_versions {
        bigint id PK
        varchar version UK "XGB-1.0.0"
        varchar algorithm
        varchar artifact_name
        timestamp trained_at
        int dataset_size
        int training_size
        int testing_size
        decimal accuracy "nullable"
        decimal precision_score "nullable"
        decimal recall "nullable"
        decimal f1_score "nullable"
        json parameters_json
        json features_json
        varchar checksum
        boolean is_active
    }

    import_histories {
        bigint id PK
        bigint user_id FK
        varchar original_name
        varchar stored_name
        varchar file_hash
        int total_rows
        int success_rows
        int failed_rows
        int duplicate_rows
        int invalid_rows
        json column_mapping_json
        json error_report_json
        enum status "PENDING | PREVIEW | COMMITTED | FAILED"
    }

    audit_logs {
        bigint id PK
        bigint user_id FK "nullable"
        varchar action
        varchar entity
        bigint entity_id
        varchar description
        varchar ip_address
        varchar user_agent
    }

    settings {
        bigint id PK
        varchar setting_key UK
        text setting_value
        varchar type
        varchar group_name
        varchar description
        bigint updated_by FK
    }
```

---

## Catatan Penamaan Kolom

Dua kolom dinamai berbeda dari sebutan umumnya karena nama aslinya **reserved word
MySQL**:

| Sebutan umum | Nama kolom | Alasan |
|---|---|---|
| `precision` | **`precision_score`** | `PRECISION` reserved (bagian dari `DOUBLE PRECISION`) |
| `key`, `value` | **`setting_key`**, **`setting_value`** | `KEY` reserved |

Pada `metadata.json` ML API dan pada tampilan antarmuka, metrik tetap disebut
`precision`. Pemetaannya dilakukan di `ModelVersionController::sync()`.

---

## Strategi Foreign Key

| Relasi | On Delete | Alasan |
|---|---|---|
| `measurements.toddler_id → toddlers.id` | RESTRICT | Riwayat pengukuran tidak boleh hilang diam-diam |
| `predictions.measurement_id → measurements.id` | RESTRICT | Histori prediksi wajib tetap dapat ditelusuri |
| `predictions.toddler_id → toddlers.id` | RESTRICT | Idem |
| `predictions.model_version_id → model_versions.id` | SET NULL | Model lama boleh dihapus tanpa merusak histori |
| `measurements.import_history_id → import_histories.id` | SET NULL | Batch boleh dihapus, data pengukuran tetap |
| `*.created_by`, `*.predicted_by`, `import_histories.user_id` | RESTRICT | Menjaga jejak pertanggungjawaban |
| `audit_logs.user_id → users.id` | SET NULL | Audit bertahan meski akun dihapus |
| `settings.updated_by → users.id` | SET NULL | Idem |

Seluruh foreign key benar-benar ditegakkan oleh basis data, bukan hanya di level
aplikasi. Penghapusan yang ditolak ditangani lebih dulu di service layer dengan
pesan yang jelas, sehingga pengguna tidak melihat error basis data.

---

## Indeks

| Tabel | Indeks |
|---|---|
| `users` | `username` (UK), `email` (UK), `role`, `status` |
| `toddlers` | `code` (UK), `nik`, `name`, `gender`, `village`, `posyandu`, `(village, posyandu)` |
| `measurements` | `(toddler_id, measurement_date)` (UK), `(toddler_id, measurement_date)`, `source` |
| `predictions` | `request_id` (UK), `predicted_at`, `prediction_label`, `(toddler_id, predicted_at)` |
| `model_versions` | `version` (UK), `is_active` |
| `import_histories` | `file_hash`, `status` |
| `audit_logs` | `action`, `created_at`, `(entity, entity_id)` |
| `settings` | `setting_key` (UK), `group_name` |

Batasan unik `(toddler_id, measurement_date)` mencegah satu balita memiliki dua
pemeriksaan pada tanggal yang sama, baik lewat input manual maupun import.

---

## Aturan Data Penting

### Kode balita

Format `BLT-000001`, dihitung dari **kode terbesar yang ada**, bukan dari jumlah
baris, agar penghapusan data tidak menyebabkan kode bentrok.

### `age_months` adalah snapshot

Usia dihitung dari `toddlers.birth_date` dan `measurements.measurement_date`, lalu
**disimpan**. Usia riwayat tidak pernah dihitung dari tanggal hari ini, sehingga
data lama tidak bergeser seiring waktu. Perhitungan memakai bulan penuh
(pembulatan ke bawah).

### `input_snapshot_json`

Setiap prediksi menyimpan salinan fitur yang benar-benar dipakai:

```json
{ "gender": "L", "age_months": 30, "weight_kg": 11.4, "height_cm": 86.5 }
```

Bila data pemeriksaan disunting kemudian, hasil prediksi lama tetap dapat
dipertanggungjawabkan karena masukannya tersimpan utuh.

### Kolom metrik boleh NULL

`accuracy`, `precision_score`, `recall`, `f1_score` bersifat nullable dan tetap
`NULL` sampai ada hasil training sebenarnya. Antarmuka menampilkannya sebagai `-`,
bukan `0`.

---

## Perintah Berguna

```bash
php artisan migrate                 # jalankan migrasi
php artisan migrate:fresh --seed    # ulang dari nol + data sintetis (non-production)
php artisan migrate:status
php artisan db:show --counts
```

Cadangan basis data:

```bash
mysqldump -u <user> -p sipresti > sipresti-$(date +%F).sql
```
