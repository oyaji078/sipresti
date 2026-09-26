<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identitas Aplikasi
    |--------------------------------------------------------------------------
    | Dipakai pada sidebar, halaman login, header laporan, dan judul halaman.
    | Diubah lewat .env agar branding mudah disesuaikan.
    */
    'instansi' => env('APP_INSTANSI', 'Puskesmas Lepak'),
    'short_name' => env('APP_SHORT_NAME', 'SIPRESTI'),
    'long_name' => env('APP_LONG_NAME', 'Sistem Prediksi Risiko Stunting Balita'),

    /*
    |--------------------------------------------------------------------------
    | Machine Learning API (Vercel)
    |--------------------------------------------------------------------------
    | Browser tidak pernah memanggil endpoint ini. Seluruh permintaan berasal
    | dari server Laravel melalui MlPredictionService.
    */
    'ml' => [
        'url' => rtrim((string) env('ML_API_URL', ''), '/'),
        'key' => env('ML_API_KEY', ''),
        'timeout' => (int) env('ML_API_TIMEOUT', 10),
        'connect_timeout' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Disclaimer Hasil Prediksi
    |--------------------------------------------------------------------------
    | Wajib tampil di setiap halaman yang menampilkan hasil prediksi.
    */
    'disclaimer' => 'Hasil prediksi merupakan alat bantu pendukung keputusan dan tidak menggantikan pemeriksaan maupun penilaian tenaga kesehatan.',

    /*
    |--------------------------------------------------------------------------
    | Validasi Antropometri
    |--------------------------------------------------------------------------
    | Batas teknis untuk menolak nilai yang mustahil, BUKAN ambang klinis.
    | Ambang klinis hanya boleh diisi dari pedoman resmi atau dataset asli.
    */
    'measurement' => [
        'age_months_min' => 0,
        'age_months_max' => 60,
        'weight_kg_min' => 0.5,
        'weight_kg_max' => 60.0,
        'height_cm_min' => 20.0,
        'height_cm_max' => 150.0,
        'muac_cm_min' => 5.0,
        'muac_cm_max' => 30.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Import E-PPBGM
    |--------------------------------------------------------------------------
    */
    'import' => [
        'max_file_size_kb' => 10240,
        'max_rows' => 20000,
        'allowed_extensions' => ['csv', 'xlsx', 'xls'],
    ],

];
