<?php

use App\Http\Controllers\CMS\AboutPageController;
use App\Http\Controllers\CMS\AsesiController;
use App\Http\Controllers\CMS\AsesmenController;
use App\Http\Controllers\CMS\AsesorController;
use App\Http\Controllers\CMS\CalendarController;
use App\Http\Controllers\CMS\CategorySkemaSertifikasiController;
use App\Http\Controllers\CMS\DivisionController;
use App\Http\Controllers\CMS\ElemenKompetensiController;
use App\Http\Controllers\CMS\FaqController;
use App\Http\Controllers\CMS\GalleryAlbumController;
use App\Http\Controllers\CMS\HeroBannerController;
use App\Http\Controllers\CMS\MapaTemplateController;
use App\Http\Controllers\CMS\MemberController;
use App\Http\Controllers\CMS\MitraController;
use App\Http\Controllers\CMS\NewsArticleController;
use App\Http\Controllers\CMS\NewsCategoryController;
use App\Http\Controllers\CMS\PendaftaranSertifikasiController;
use App\Http\Controllers\CMS\PenjadwalanAsesmenController;
use App\Http\Controllers\CMS\ReviewApl01Controller;
use App\Http\Controllers\CMS\SkemaSertifikasiController;
use App\Http\Controllers\CMS\TinyMceController;
use App\Http\Controllers\CMS\TUKController;
use App\Http\Controllers\DokumenPendaftaranController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth'])->group(function () {

    // Penjadwalan Asesmen
    Route::prefix('penjadwalan-asesmen')->name('penjadwalan-asesmen.')->group(function () {
        Route::get('/', [PenjadwalanAsesmenController::class, 'index'])->name('index');
        Route::get('/{kodePendaftaran}/create', [PenjadwalanAsesmenController::class, 'create'])->name('create');
        Route::post('/{kodePendaftaran}', [PenjadwalanAsesmenController::class, 'store'])->name('store');
    });

    // Review FR.APL.01 (admin LSP)
    Route::prefix('review-apl01')->name('review-apl01.')->group(function () {
        Route::get('/', [ReviewApl01Controller::class, 'index'])->name('index');
        Route::get('/{dokumen}', [ReviewApl01Controller::class, 'show'])->whereNumber('dokumen')->name('show');
        Route::post('/{dokumen}', [ReviewApl01Controller::class, 'update'])->whereNumber('dokumen')->name('update');
        Route::get('/{dokumen}/bukti/{bukti}', [ReviewApl01Controller::class, 'bukti'])->whereNumber(['dokumen', 'bukti'])->name('bukti');
        Route::get('/{dokumen}/relevan/{relevan}', [ReviewApl01Controller::class, 'relevan'])->whereNumber(['dokumen', 'relevan'])->name('relevan');
        Route::get('/{dokumen}/tanda-tangan/{pihak}', [ReviewApl01Controller::class, 'tandaTangan'])->whereNumber('dokumen')->name('tanda-tangan');
    });

    // Asesmen
    Route::prefix('asesmen')->name('asesmen.')->group(function () {
        Route::get('/', [AsesmenController::class, 'index'])->name('index');
        Route::get('/{jadwalAsesmen}', [AsesmenController::class, 'show'])->name('show');
    });

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

    Route::prefix('sertifikat/skema-sertifikasi/{skema_sertifikasi}/unit-kompetensi/{unit_kompetensi}')
        ->name('skema-sertifikasi.unit-kompetensi.')
        ->group(function () {
            Route::get('elemen', [ElemenKompetensiController::class, 'edit'])->name('elemen.edit');
            Route::put('elemen', [ElemenKompetensiController::class, 'update'])->name('elemen.update');
        });

    // Template FR.MAPA.01/02 per skema + metode (kt/tpd/vp)
    Route::get('sertifikat/skema-sertifikasi/{skema_sertifikasi}/mapa/{metode}', [MapaTemplateController::class, 'edit'])
        ->whereIn('metode', ['kt', 'tpd', 'vp', 'umum'])->name('skema-sertifikasi.mapa.edit');
    Route::put('sertifikat/skema-sertifikasi/{skema_sertifikasi}/mapa/{metode}', [MapaTemplateController::class, 'update'])
        ->whereIn('metode', ['kt', 'tpd', 'vp', 'umum'])->name('skema-sertifikasi.mapa.update');
    Route::get('mapa-template/ttd/{penandatangan}', [MapaTemplateController::class, 'ttd'])
        ->whereNumber('penandatangan')->name('mapa-template.ttd');

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
    // Asesor
    Route::resource('asesors', AsesorController::class);
});

require __DIR__.'/auth.php';
