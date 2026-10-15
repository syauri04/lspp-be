<?php

use App\Services\Muk\Dokumen\Apl01Handler;
use App\Services\Muk\Dokumen\Apl02Handler;
use App\Services\Muk\Dokumen\Mapa01Handler;
use App\Services\Muk\Dokumen\Mapa02Handler;

/*
|--------------------------------------------------------------------------
| MUK Paperless (sertifikasi.lspp306.com)
|--------------------------------------------------------------------------
| Satu titik konfigurasi untuk API /api/muk. Aturan bisnis yang mungkin
| berubah (lama token, URL FE, dst) ditaruh di sini, bukan di controller.
*/

return [

    // URL web MUK, dipakai untuk deep link di email notifikasi.
    'frontend_url' => rtrim(env('MUK_URL', 'http://localhost:3001'), '/'),

    // Lama berlaku token login MUK (menit). Diset per token, bukan global
    // sanctum.expiration, supaya sesi lspp-fe tidak ikut terdampak.
    'token_ttl_minutes' => (int) env('MUK_TOKEN_TTL_MINUTES', 480),

    // Nama token di personal_access_tokens, untuk membedakan dari token lspp-fe.
    'token_name' => 'muk',

    // APL.02 baru bisa dibuka asesi kalau status APL.01 salah satu dari ini
    // (keputusan terbuka #c). Ubah di sini kalau aturan bisnis berubah.
    'apl02_butuh_status_apl01' => ['menunggu_review', 'selesai'],

    // Registry dokumen: slug URL -> handler. Tambah dokumen baru (MAPA, AK, IA) di sini.
    'dokumen_handlers' => [
        'apl-01' => Apl01Handler::class,
        'apl-02' => Apl02Handler::class,
        'mapa-01' => Mapa01Handler::class,
        'mapa-02' => Mapa02Handler::class,
    ],

    // Tanda tangan (PNG base64 dari canvas FE).
    'tanda_tangan' => [
        'disk' => 'local', // privat: storage/app/private
        'max_bytes' => 300 * 1024,
        'max_width' => 3000,
        'max_height' => 1500,
        'min_width' => 50,
        'min_height' => 20,
    ],

    // Batas percobaan login per (email + IP) dan per IP saja, per menit.
    'login_max_attempts_per_email' => 5,
    'login_max_attempts_per_ip' => 20,

];
