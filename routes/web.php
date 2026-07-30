<?php

use App\Http\Controllers\CMS\AboutPageController;
use App\Http\Controllers\CMS\AsesiController;
use App\Http\Controllers\CMS\CalendarController;
use App\Http\Controllers\CMS\CategorySkemaSertifikasiController;
use App\Http\Controllers\CMS\DivisionController;
use App\Http\Controllers\CMS\FaqController;
use App\Http\Controllers\CMS\GalleryAlbumController;
use App\Http\Controllers\CMS\HeroBannerController;
use App\Http\Controllers\CMS\MemberController;
use App\Http\Controllers\CMS\MitraController;
use App\Http\Controllers\CMS\NewsArticleController;
use App\Http\Controllers\CMS\NewsCategoryController;
use App\Http\Controllers\CMS\PendaftaranSertifikasiController;
use App\Http\Controllers\CMS\SkemaSertifikasiController;
use App\Http\Controllers\CMS\TinyMceController;
use App\Http\Controllers\CMS\TUKController;
use App\Http\Controllers\DokumenPendaftaranController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth'])->group(function () {

    // Pendaftaran Sertifikasi
    Route::prefix('pendaftaran')
        ->name('pendaftaran.')
        ->group(function () {
            Route::get('/', [PendaftaranSertifikasiController::class, 'index'])
                ->name('index');

            Route::get('/{pendaftaran:kode_pendaftaran}', [PendaftaranSertifikasiController::class, 'show'])
                ->name('show');

            Route::post('/{pendaftaran:kode_pendaftaran}/approve', [PendaftaranSertifikasiController::class, 'approve'])
                ->name('approve');

            Route::post('/{pendaftaran:kode_pendaftaran}/reject', [PendaftaranSertifikasiController::class, 'reject'])
                ->name('reject');
        });

    // view Dokumen
    Route::get('/dokumen/{dokumen}/preview', [DokumenPendaftaranController::class, 'preview'])
        ->middleware('signed')
        ->name('dokumen.preview');


    Route::post('/tinymce/upload', [TinyMceController::class, 'upload'])
        ->name('tinymce.upload');

    Route::get('/dashboard', function () {
        return view('layouts.dashboard');
    })->name('dashboard');

    // Hero Banner
    Route::resource(
        'hero-banner',
        HeroBannerController::class
    );

    // About Page
    Route::get(
        '/about-page',
        [AboutPageController::class, 'edit']
    )->name('about-page.edit');

    Route::post(
        '/about-page',
        [AboutPageController::class, 'update']
    )->name('about-page.update');

    // Sturktur Organisasi
    Route::resource('organizational/divisions', DivisionController::class);

    Route::resource('organizational/members', MemberController::class);

    // TUK
    Route::resource('tuks', TUKController::class);

    // Berita
    Route::resource(
        'news/news-categories',
        NewsCategoryController::class
    );

    Route::resource(
        'news/news-articles',
        NewsArticleController::class
    );

    // Galeri
    Route::resource(
        'gallery-albums',
        GalleryAlbumController::class
    );

    // Route tambahan untuk manajemen foto per item
    Route::delete(
        'gallery-photos/{galleryPhoto}',
        [GalleryAlbumController::class, 'destroyPhoto']
    )->name('gallery-photos.destroy');

    Route::patch(
        'gallery-photos/{galleryPhoto}/toggle',
        [GalleryAlbumController::class, 'togglePhoto']
    )->name('gallery-photos.toggle');

    Route::post(
        'gallery-photos/reorder',
        [GalleryAlbumController::class, 'reorderPhotos']
    )->name('gallery-photos.reorder');


    // Sturktur Organisasi
    Route::resource('sertifikat/skema-categories', CategorySkemaSertifikasiController::class);

    Route::resource('sertifikat/skema-sertifikasi', SkemaSertifikasiController::class);

    // Calendar
    Route::resource('calendars', CalendarController::class);
    // Faqs
    Route::resource('faqs', FaqController::class);

    // Mitra
    Route::resource(
        'mitras',
        MitraController::class
    );

    // Asesi
    Route::resource('asesis', AsesiController::class);
});

require __DIR__ . '/auth.php';
