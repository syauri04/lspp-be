<?php

/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
| Sebelumnya file ini tidak ada sehingga default framework berlaku
| (allowed_origins = '*'). Sekarang dibatasi ke origin FE yang dikenal:
| lspp-fe (website utama) dan muk (sertifikasi paperless).
|
| Atur lewat .env, dipisah koma, contoh produksi:
| CORS_ALLOWED_ORIGINS=https://lspp306.com,https://www.lspp306.com,https://sertifikasi.lspp306.com
|
| Auth memakai Bearer token (bukan cookie), jadi supports_credentials tetap false.
| Webhook DOKU dipanggil server-ke-server, tidak terpengaruh CORS.
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:3001'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Nama berkas unduhan (SK / e-sertifikat) dibaca FE dari header ini.
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 3600,

    'supports_credentials' => false,

];
