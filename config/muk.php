<?php

use App\Services\Muk\Dokumen\Ak01Handler;
use App\Services\Muk\Dokumen\Ak02Handler;
use App\Services\Muk\Dokumen\Ak03Handler;
use App\Services\Muk\Dokumen\Ak05Handler;
use App\Services\Muk\Dokumen\Ak06Handler;
use App\Services\Muk\Dokumen\Ak07Handler;
use App\Services\Muk\Dokumen\Apl01Handler;
use App\Services\Muk\Dokumen\Apl02Handler;
use App\Services\Muk\Dokumen\Ia01Handler;
use App\Services\Muk\Dokumen\Ia02Handler;
use App\Services\Muk\Dokumen\Ia03Handler;
use App\Services\Muk\Dokumen\Ia04aHandler;
use App\Services\Muk\Dokumen\Ia04bHandler;
use App\Services\Muk\Dokumen\Ia06aHandler;
use App\Services\Muk\Dokumen\Ia06bHandler;
use App\Services\Muk\Dokumen\Ia06cHandler;
use App\Services\Muk\Dokumen\Ia08Handler;
use App\Services\Muk\Dokumen\Ia09Handler;
use App\Services\Muk\Dokumen\Ia10Handler;
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
        'ak-07' => Ak07Handler::class,
        'ak-01' => Ak01Handler::class,
        'ia-01' => Ia01Handler::class,
        'ia-02' => Ia02Handler::class,
        'ia-03' => Ia03Handler::class,
        'ia-04a' => Ia04aHandler::class,
        'ia-04b' => Ia04bHandler::class,
        'ia-08' => Ia08Handler::class,
        'ia-09' => Ia09Handler::class,
        'ia-10' => Ia10Handler::class,
        'ia-06a' => Ia06aHandler::class,
        'ia-06b' => Ia06bHandler::class,
        'ia-06c' => Ia06cHandler::class,
        'ak-02' => Ak02Handler::class,
        'ak-03' => Ak03Handler::class,
        'ak-05' => Ak05Handler::class,
        'ak-06' => Ak06Handler::class,
    ],

    // Email notifikasi alur dokumen MUK.
    'notifikasi' => [
        // Email ke asesor saat asesi menyetujui dokumen yang diisi asesor (AK.07, AK.01, IA, AK.02, ...).
        // Nonaktif: asesmen selalu tatap muka di kantor LSP, asesor melihat statusnya langsung di MUK.
        'asesor_setelah_disetujui' => (bool) env('MUK_EMAIL_ASESOR_DISETUJUI', false),
    ],

    // Zona waktu untuk tanggal pada dokumen (mis. tanggal tanda tangan penyusun/validator saat dikirim).
    'zona_waktu' => env('MUK_TIMEZONE', 'Asia/Jakarta'),

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
