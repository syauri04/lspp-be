<?php

use App\Http\Controllers\CMS\AboutPageController;
use App\Http\Controllers\CMS\CalendarController;
use App\Http\Controllers\CMS\CategorySkemaSertifikasiController;
use App\Http\Controllers\CMS\DivisionController;
use App\Http\Controllers\CMS\FaqController;
use App\Http\Controllers\CMS\GalleryAlbumController;
use App\Http\Controllers\CMS\HeroBannerController;
use App\Http\Controllers\CMS\MemberController;
use App\Http\Controllers\CMS\NewsArticleController;
use App\Http\Controllers\CMS\NewsCategoryController;
use App\Http\Controllers\CMS\SkemaSertifikasiController;
use App\Http\Controllers\CMS\TinyMceController;
use App\Http\Controllers\CMS\TUKController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth'])->group(function () {


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
    // Calendar
    Route::resource('faqs', FaqController::class);
});

require __DIR__ . '/auth.php';
