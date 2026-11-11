<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\PenilaianMuk;
use App\Services\Muk\AksesAdminMuk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * POST /muk/{penilaian}/buka — super admin membuka penilaian di MUK (baca & cetak dokumen).
 * Kode sekali pakai dikirim di FRAGMENT URL (#kode=...) sehingga tidak ikut ke log server
 * maupun header Referer; MUK menukarnya ke API lalu menghapusnya dari address bar.
 */
class BukaMukController extends Controller
{
    public function __invoke(Request $request, PenilaianMuk $penilaian, AksesAdminMuk $akses): RedirectResponse
    {
        Gate::authorize('kelola-sertifikasi');

        $kode = $akses->buatKode($request->user(), $penilaian);

        return redirect()->away(config('muk.frontend_url').'/admin/masuk#kode='.$kode);
    }
}
