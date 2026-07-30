<?php

use App\Http\Controllers\API\AboutPageController;
use App\Http\Controllers\API\CalendarsController;
use App\Http\Controllers\API\FaqController;
use App\Http\Controllers\API\GalleryController;
use App\Http\Controllers\API\HeroBannerController;
use App\Http\Controllers\API\NewsArticleController;
use App\Http\Controllers\API\SertifikasiController;
use App\Http\Controllers\API\StrukturController;
use App\Http\Controllers\API\TukController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\{
    ChangePasswordController,
    GoogleLoginController,
    RegisterController,
    LoginController,
    LogoutController,
    MeController,
    VerifyEmailController,
    ResendVerificationController,
    SetPasswordController,
    UpdateProfileController,
};

use App\Http\Controllers\API\MitraController;
use App\Http\Controllers\API\Pendaftaran\CheckoutController;
use App\Http\Controllers\Api\Pendaftaran\MidtransWebhookController;
use App\Http\Controllers\API\Pendaftaran\PendaftaranSertifikasiController;

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

    Route::middleware('auth:sanctum')->group(function () {
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
| CMS
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum', 'throttle:60,1')
    ->group(function () {

        Route::get('/hero-banners', [HeroBannerController::class, 'index']);
    });

Route::middleware([
    'auth:sanctum',
    'throttle:60,1'
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
            'home'
        ]);

        // List berita + pagination
        Route::get('/news', [
            NewsArticleController::class,
            'index'
        ]);

        // Detail berita
        Route::get('/news/{slug}', [
            NewsArticleController::class,
            'detail'
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
                'categories'
            ]);

            // List
            Route::get('/', [
                SertifikasiController::class,
                'index'
            ]);

            // Detail
            Route::get('/{slug}', [
                SertifikasiController::class,
                'detail'
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Calendars
        |--------------------------------------------------------------------------
        */

        Route::get('/calendars', [
            CalendarsController::class,
            'index'
        ]);

        /*
        |--------------------------------------------------------------------------
        | FAQ
        |--------------------------------------------------------------------------
        */

        Route::get('/faqs', [
            FaqController::class,
            'index'
        ]);

        // Mitra
        Route::get('/mitras', [
            MitraController::class,
            'index'
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
            'show'
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
            'show'
        ]);

        Route::post('/{pendaftaran:kode_pendaftaran}/snap-token', [
            CheckoutController::class,
            'snapToken'
        ])->middleware('throttle:20,1');
    });

/*
|--------------------------------------------------------------------------
| Midtrans Webhook
|--------------------------------------------------------------------------
| SENGAJA di luar semua middleware auth:sanctum di atas -- ini dipanggil
| langsung oleh server Midtrans, bukan oleh asesi/admin, jadi tidak punya
| Bearer token sama sekali. Keamanan bergantung sepenuhnya pada verifikasi
| signature_key di dalam MidtransWebhookController::signatureValid().
|
| Daftarkan URL ini di dashboard Midtrans:
| Settings > Configuration > Payment Notification URL
| -> https://domainmu.com/api/midtrans/callback
|
| withoutMiddleware('throttle:api') untuk lepas dari rate limit default 60/menit
| per-IP bawaan Laravel -- IP server Midtrans dipakai bersama oleh banyak merchant
| lain, jadi throttle per-IP standar berisiko salah membatasi.
*/

Route::post('/midtrans/callback', [MidtransWebhookController::class, 'handle']);
