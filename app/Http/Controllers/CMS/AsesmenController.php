<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\JadwalAsesmen;
use Illuminate\Http\Request;

class AsesmenController extends Controller
{
    public function index(Request $request)
    {
        $jadwals = JadwalAsesmen::with(['pendaftaran.asesi', 'pendaftaran.skemaSertifikasi', 'asesor', 'tuk'])
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->latest('tanggal_asesmen')
            ->paginate(20)
            ->withQueryString();

        return view('cms.asesmen.index', compact('jadwals'));
    }

    public function show(JadwalAsesmen $jadwalAsesmen)
    {
        $jadwalAsesmen->load(['pendaftaran.asesi', 'pendaftaran.skemaSertifikasi', 'asesor', 'tuk']);

        return view('cms.asesmen.show', compact('jadwalAsesmen'));
    }
}
