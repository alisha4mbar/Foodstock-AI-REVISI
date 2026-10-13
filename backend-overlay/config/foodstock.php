<?php

// Parameter bisnis FoodStock AI. Ambang status disimpan di konfigurasi, bukan di kode
// (checklist Desain Arsitektur Bagian 18). Nilai shortage/surplus dapat ditimpa Admin
// lewat tabel `settings` (PUT /api/v1/settings/model).
return [
    'forecast' => [
        'season_length' => 12,   // musiman 12 bulan
        'min_points'    => 30,   // 24 latih (2 musim) + 6 uji
        'holdout'       => 6,
        'horizon'       => 3,    // proyeksi 3 bulan ke depan
        'model_version' => 'holt-winters-additive-v1',
        'grid' => [
            'alpha' => [0.1, 0.3, 0.5, 0.7],
            'beta'  => [0.01, 0.05, 0.1, 0.2],
            'gamma' => [0.1, 0.3, 0.5],
        ],
    ],
    'status' => [
        // shortage jika stok < max(kebutuhan, safe_stock) * (1 - margin/100)
        'shortage_margin_pct' => 0,
        // surplus jika stok > kebutuhan * (1 + margin/100)  (50 => 1,5x kebutuhan)
        'surplus_margin_pct'  => 50,
    ],
    'login_max_attempts' => 5,

    // Email Admin Utama (tanpa akun demo). Siapa pun yang login dengan email ini otomatis menjadi
    // Admin Utama; password yang dimasukkan pada login pertama menjadi password-nya.
    // Isi di .env, pisahkan dengan koma bila lebih dari satu:  FOODSTOCK_ADMIN_EMAILS=admin@kampus.ac.id
    'admin_emails' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('FOODSTOCK_ADMIN_EMAILS', ''))
    ))),
];
