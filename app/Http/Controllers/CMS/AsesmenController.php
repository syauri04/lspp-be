<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\JadwalAsesmen;
use App\Models\PenilaianMuk;
use Illuminate\Http\Request;

class AsesmenController extends Controller
{
    /** Filter hasil asesmen (dari penilaian MUK). */
    private const HASIL = [
        'kompeten' => PenilaianMuk::STATUS_SELESAI_KOMPETEN,
        'belum_kompeten' => PenilaianMuk::STATUS_SELESAI_BELUM_KOMPETEN,
    ];

    public function index(Request $request)
    {
        $jadwals = JadwalAsesmen::with(['pendaftaran.asesi', 'pendaftaran.skemaSertifikasi', 'asesor', 'tuk', 'penilaianMuk'])
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->when(isset(self::HASIL[$request->hasil]), function ($q) use ($request) {
                $q->whereHas('penilaianMuk', fn ($p) => $p->where('status', self::HASIL[$request->hasil]));
            })
            ->latest('tanggal_asesmen')
            ->paginate(20)
            ->withQueryString();

        return view('cms.asesmen.index', compact('jadwals'));
    }

    public function show(JadwalAsesmen $jadwalAsesmen)
    {
        $jadwalAsesmen->load([
            'pendaftaran.asesi',
            'pendaftaran.skemaSertifikasi',
            'asesor',
            'tuk',
            'penilaianMuk.dokumenPenilaian.jenisDokumen',
        ]);

        return view('cms.asesmen.show', compact('jadwalAsesmen'));
    }
}
