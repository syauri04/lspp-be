<?php

use App\Http\Controllers\API\AboutPageController;
use App\Http\Controllers\API\CalendarsController;
use App\Http\Controllers\API\FaqController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\API\HeroBannerController;
use App\Http\Controllers\API\NewsArticleController;
use App\Http\Controllers\API\SertifikasiController;
use App\Http\Controllers\API\StrukturController;
use App\Http\Controllers\API\TukController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\{
    RegisterController,
    LoginController,
    LogoutController,
    MeController,
    VerifyEmailController,
    ResendVerificationController,
};

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
        | Calendars
        |--------------------------------------------------------------------------
        */

        Route::get('/faqs', [
            FaqController::class,
            'index'
        ]);
    });
