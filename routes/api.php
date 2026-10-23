<?php

use App\Http\Controllers\API\AboutPageController;
use App\Http\Controllers\API\Auth\ChangePasswordController;
use App\Http\Controllers\API\Auth\GoogleLoginController;
use App\Http\Controllers\API\Auth\LoginController;
use App\Http\Controllers\API\Auth\LogoutController;
use App\Http\Controllers\API\Auth\MeController;
use App\Http\Controllers\API\Auth\RegisterController;
use App\Http\Controllers\API\Auth\ResendVerificationController;
use App\Http\Controllers\API\Auth\SetPasswordController;
use App\Http\Controllers\API\Auth\UpdateProfileController;
use App\Http\Controllers\API\Auth\VerifyEmailController;
use App\Http\Controllers\API\CalendarsController;
use App\Http\Controllers\API\FaqController;
use App\Http\Controllers\API\GalleryController;
use App\Http\Controllers\API\HeroBannerController;
use App\Http\Controllers\API\MitraController;
use App\Http\Controllers\API\Muk\Apl01BuktiController as MukApl01BuktiController;
use App\Http\Controllers\API\Muk\Apl01RelevanController as MukApl01RelevanController;
use App\Http\Controllers\API\Muk\Apl02BuktiController as MukApl02BuktiController;
use App\Http\Controllers\API\Muk\Auth\MukAuthController;
use App\Http\Controllers\API\Muk\DokumenController as MukDokumenController;
use App\Http\Controllers\API\Muk\MetodeController as MukMetodeController;
use App\Http\Controllers\API\Muk\PenilaianController as MukPenilaianController;
use App\Http\Controllers\API\NewsArticleController;
use App\Http\Controllers\API\Pendaftaran\CheckoutController;
use App\Http\Controllers\API\Pendaftaran\DokuWebhookController;
use App\Http\Controllers\API\Pendaftaran\PendaftaranSertifikasiController;
use App\Http\Controllers\API\SertifikasiController;
use App\Http\Controllers\API\StrukturController;
use App\Http\Controllers\API\TukController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AUTH (FE) — HARUS DI LUAR group auth:sanctum CMS
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('register', RegisterController::class)->middleware('throttle:5,1');
    Route::post('login', LoginController::class)->middleware('throttle:5,1');

    Route::post('email/verify', VerifyEmailController::class)
        ->middleware('signed')
        ->name('api.asesi.verify-email');

    Route::post('email/resend', ResendVerificationController::class)
        ->middleware('throttle:3,1');

    // asesi.website: tolak token asesor & token khusus MUK di endpoint akun lspp-fe
    Route::middleware(['auth:sanctum', 'asesi.website'])->group(function () {
        Route::post('logout', LogoutController::class);
        Route::get('me', MeController::class);

        Route::post('profile', UpdateProfileController::class);
        Route::post('set-password', SetPasswordController::class)->middleware('throttle:5,1');
        Route::post('change-password', ChangePasswordController::class)->middleware('throttle:5,1');
    });

    Route::post('google', GoogleLoginController::class)->middleware('throttle:5,1');
});

/*
|--------------------------------------------------------------------------
| MUK Paperless (sertifikasi.lspp306.com)
|--------------------------------------------------------------------------
| Login terpisah dari lspp-fe: token MUK hanya berability "muk:asesi" /
| "muk:asesor" dan berumur config('muk.token_ttl_minutes'). Asesor HANYA
| bisa login di sini. Peran dicek oleh middleware 'muk.role'.
*/

Route::prefix('muk')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:muk-login')->group(function () {
            Route::post('asesi/login', [MukAuthController::class, 'loginAsesi']);
            Route::post('asesor/login', [MukAuthController::class, 'loginAsesor']);
        });

        Route::middleware(['auth:sanctum', 'muk.role:asesi,asesor', 'throttle:60,1'])->group(function () {
            Route::get('me', [MukAuthController::class, 'me']);
            Route::post('logout', [MukAuthController::class, 'logout']);
        });
    });

    // Terbuka untuk asesi & asesor; hasil di-scope per pemilik di controller.
    Route::middleware(['auth:sanctum', 'muk.role:asesi,asesor', 'throttle:60,1'])->group(function () {
        Route::get('penilaian', [MukPenilaianController::class, 'index']);
        Route::get('penilaian/{penilaian}', [MukPenilaianController::class, 'show'])->whereNumber('penilaian');

        // Dokumen (generik per slug: apl-01, apl-02, ...). Baca: asesi & asesor (dicek policy).
        Route::get('penilaian/{penilaian}/dokumen/{slug}', [MukDokumenController::class, 'show'])->whereNumber('penilaian');
        Route::get('penilaian/{penilaian}/dokumen/{slug}/tanda-tangan/{pihak}', [MukDokumenController::class, 'gambarTandaTangan'])->whereNumber('penilaian');
        Route::get('penilaian/{penilaian}/dokumen/{slug}/penandatangan/{penandatangan}', [MukDokumenController::class, 'gambarPenandatangan'])
            ->whereNumber(['penilaian', 'penandatangan']);
        Route::get('penilaian/{penilaian}/dokumen/apl-01/bukti/{bukti}', [MukApl01BuktiController::class, 'show'])
            ->whereNumber(['penilaian', 'bukti']);
        Route::get('penilaian/{penilaian}/dokumen/apl-02/bukti/{bukti}/file', [MukApl02BuktiController::class, 'show'])
            ->whereNumber(['penilaian', 'bukti']);
        Route::get('penilaian/{penilaian}/dokumen/apl-01/relevan/{relevan}/file', [MukApl01RelevanController::class, 'show'])
            ->whereNumber(['penilaian', 'relevan']);
    });

    // Tinjauan asesor (FR.APL.02) & penentuan metode asesmen.
    Route::middleware(['auth:sanctum', 'muk.role:asesor', 'throttle:muk-aksi'])
        ->prefix('penilaian/{penilaian}')
        ->whereNumber('penilaian')
        ->group(function () {
            Route::post('dokumen/{slug}/review', [MukDokumenController::class, 'review']);
            // Pola disiapkan asesor -> dijawab asesi -> dinilai asesor (mis. FR.IA.03).
            Route::patch('dokumen/{slug}/persiapan', [MukDokumenController::class, 'persiapan']);
            Route::post('dokumen/{slug}/buka', [MukDokumenController::class, 'buka']);
            Route::patch('dokumen/{slug}/penilaian', [MukDokumenController::class, 'penilaian']);
            Route::post('dokumen/{slug}/nilai', [MukDokumenController::class, 'nilai']);
            Route::post('metode', [MukMetodeController::class, 'store']);
        });

    // Unggah / hapus berkas bukti persyaratan dasar FR.APL.01 (asesi).
    Route::middleware(['auth:sanctum', 'muk.role:asesi', 'throttle:muk-aksi'])
        ->prefix('penilaian/{penilaian}/dokumen/apl-01/bukti/{bukti}')
        ->whereNumber(['penilaian', 'bukti'])
        ->group(function () {
            Route::post('/', [MukApl01BuktiController::class, 'store']);
            Route::delete('/', [MukApl01BuktiController::class, 'destroy']);
        });

    // Lampiran "bukti yang relevan" FR.APL.01 (asesi): baris, link & berkas.
    Route::middleware(['auth:sanctum', 'muk.role:asesi', 'throttle:muk-aksi'])
        ->prefix('penilaian/{penilaian}/dokumen/apl-01/relevan')
        ->whereNumber('penilaian')
        ->group(function () {
            Route::post('/', [MukApl01RelevanController::class, 'store']);
            Route::patch('{relevan}', [MukApl01RelevanController::class, 'update'])->whereNumber('relevan');
            Route::delete('{relevan}', [MukApl01RelevanController::class, 'destroy'])->whereNumber('relevan');
            Route::post('{relevan}/file', [MukApl01RelevanController::class, 'unggah'])->whereNumber('relevan');
            Route::delete('{relevan}/file', [MukApl01RelevanController::class, 'hapusBerkas'])->whereNumber('relevan');
        });

    // Unggah / hapus berkas "bukti yang relevan" FR.APL.02 (asesi).
    Route::middleware(['auth:sanctum', 'muk.role:asesi', 'throttle:muk-aksi'])
        ->prefix('penilaian/{penilaian}/dokumen/apl-02')
        ->whereNumber('penilaian')
        ->group(function () {
            Route::post('elemen/{elemen}/bukti', [MukApl02BuktiController::class, 'store'])->whereNumber('elemen');
            Route::delete('bukti/{bukti}/file', [MukApl02BuktiController::class, 'destroy'])->whereNumber('bukti');
        });

    // Pengisian dokumen oleh pengisinya (asesi: APL; asesor: MAPA) — dicek policy 'isi'.
    // Rate limit per user: autosave lebih longgar, ttd/submit lebih ketat.
    Route::middleware(['auth:sanctum', 'muk.role:asesi,asesor'])
        ->prefix('penilaian/{penilaian}/dokumen/{slug}')
        ->whereNumber('penilaian')
        ->group(function () {
            Route::patch('/', [MukDokumenController::class, 'update'])->middleware('throttle:muk-tulis');
            Route::post('tanda-tangan', [MukDokumenController::class, 'tandaTangan'])->middleware('throttle:muk-aksi');
            Route::post('submit', [MukDokumenController::class, 'submit'])->middleware('throttle:muk-aksi');
            // Persetujuan pihak kedua (asesi pada AK.07/AK.01) & tarik kembali oleh pengisi — dicek policy.
            Route::post('setujui', [MukDokumenController::class, 'setujui'])->middleware('throttle:muk-aksi');
            Route::post('tarik', [MukDokumenController::class, 'tarik'])->middleware('throttle:muk-aksi');
        });
});

/*
|--------------------------------------------------------------------------
| CMS
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum', 'throttle:60,1')
    ->group(function () {

        Route::get('/hero-banners', [HeroBannerController::class, 'index']);
    });

Route::middleware([
    'auth:sanctum',
    'throttle:60,1',
])
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | About
        |--------------------------------------------------------------------------
        */

        Route::get('/about/home', [AboutPageController::class, 'home']);

        Route::get('/about/detail', [AboutPageController::class, 'detail']);

        /*
        |--------------------------------------------------------------------------
        | News
        |--------------------------------------------------------------------------
        */

        // Homepage
        Route::get('/news/home', [
            NewsArticleController::class,
            'home',
        ]);

        // List berita + pagination
        Route::get('/news', [
            NewsArticleController::class,
            'index',
        ]);

        // Detail berita
        Route::get('/news/{slug}', [
            NewsArticleController::class,
            'detail',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Organization Structure
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/struktur-organisasi',
            [StrukturController::class, 'index']
        );

        /*
        |--------------------------------------------------------------------------
        | TUK
        |--------------------------------------------------------------------------
        */

        Route::get('/lokasi-tuk', [TukController::class, 'index']);

        /*
        |--------------------------------------------------------------------------
        | Gallery
        |--------------------------------------------------------------------------
        */
        Route::prefix('gallery')->group(function () {
            Route::get('/', [GalleryController::class, 'index']);
            Route::get('/{slug}', [GalleryController::class, 'show']);
        });

        /*
        |--------------------------------------------------------------------------
        | Sertifikasi
        |--------------------------------------------------------------------------
        */

        Route::prefix('sertifikasi')->group(function () {

            // Category
            Route::get('/categories', [
                SertifikasiController::class,
                'categories',
            ]);

            // List
            Route::get('/', [
                SertifikasiController::class,
                'index',
            ]);

            // Detail
            Route::get('/{slug}', [
                SertifikasiController::class,
                'detail',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Calendars
        |--------------------------------------------------------------------------
        */

        Route::get('/calendars', [
            CalendarsController::class,
            'index',
        ]);

        /*
        |--------------------------------------------------------------------------
        | FAQ
        |--------------------------------------------------------------------------
        */

        Route::get('/faqs', [
            FaqController::class,
            'index',
        ]);

        // Mitra
        Route::get('/mitras', [
            MitraController::class,
            'index',
        ]);
    });

/*
|--------------------------------------------------------------------------
| Pendaftaran Sertifikasi (asesi login wajib)
|--------------------------------------------------------------------------
| Endpoint 'store' throttle lebih ketat karena melibatkan upload 5 file
| sekaligus (KTP, ijazah, portfolio, pas foto, CV) — cegah abuse/spam submit.
*/

Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->prefix('pendaftaran-sertifikasi')
    ->group(function () {

        Route::get('/', [PendaftaranSertifikasiController::class, 'index']);

        Route::get('/{pendaftaran:kode_pendaftaran}', [
            PendaftaranSertifikasiController::class,
            'show',
        ]);

        Route::post('/', [PendaftaranSertifikasiController::class, 'store'])
            ->middleware('throttle:5,1');
    });

/*
|--------------------------------------------------------------------------
| Checkout (dipanggil Next.js FE, asesi login wajib)
|--------------------------------------------------------------------------
| 'show' cuma ambil info harga -- aman dipanggil kapan saja, tidak menyentuh Midtrans.
| 'snap-token' baru manggil Midtrans, hanya dipanggil saat asesi klik "Bayar Sekarang".
*/

Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->prefix('checkout')
    ->group(function () {

        Route::get('/{pendaftaran:kode_pendaftaran}', [
            CheckoutController::class,
            'show',
        ]);

        Route::post('/{pendaftaran:kode_pendaftaran}/pay', [
            CheckoutController::class,
            'pay',
        ])->middleware('throttle:20,1');

        // Route::post('/{pendaftaran:kode_pendaftaran}/snap-token', [
        //     CheckoutController::class,
        //     'snapToken'
        // ])->middleware('throttle:20,1');
    });

/*
|--------------------------------------------------------------------------
| DOKU Webhook
|--------------------------------------------------------------------------
| SENGAJA di luar semua middleware auth:sanctum di atas -- ini dipanggil
| langsung oleh server DOKU, bukan oleh asesi/admin, jadi tidak punya
| Bearer token sama sekali. Keamanan bergantung sepenuhnya pada verifikasi
| signature di dalam DokuWebhookController::signatureValid().
|
| Daftarkan URL ini di DOKU Dashboard sesuai dokumentasi resmi mereka
| (nama menu/pengaturan notifikasi URL bisa beda tergantung produk DOKU
| yang dipakai) -> https://domainmu.com/api/doku/callback
|
| Tidak ada rate limiter 'api' terdaftar di project ini, jadi route ini
| otomatis TIDAK kena throttle apapun.
*/

Route::post('/doku/callback', [DokuWebhookController::class, 'handle']);
