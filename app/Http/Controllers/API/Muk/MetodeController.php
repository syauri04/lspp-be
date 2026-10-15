<?php

namespace App\Http\Controllers\API\Muk;

use App\Http\Controllers\Controller;
use App\Http\Resources\Muk\PenilaianMukResource;
use App\Models\PenilaianMuk;
use App\Services\Muk\MetodeAsesmenService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * POST /api/muk/penilaian/{penilaian}/metode  (asesor)
 * Asesor menentukan metode asesmen (KT / TPD / VP) setelah FR.APL.02 "dapat dilanjutkan".
 */
class MetodeController extends Controller
{
    public function __construct(private MetodeAsesmenService $metode) {}

    public function store(Request $request, int $penilaian): PenilaianMukResource
    {
        $asesor = $request->user();
        $model = PenilaianMuk::query()->forAsesor($asesor)->findOrFail($penilaian);

        $validated = $request->validate([
            'metode' => ['required', Rule::in(PenilaianMuk::METODE)],
        ]);

        $this->metode->tetapkan($model, $validated['metode'], $asesor);

        $hasil = PenilaianMuk::query()->forAsesor($asesor)
            ->with(['pendaftaran.asesi', 'pendaftaran.skemaSertifikasi', 'jadwalAsesmen.asesor', 'jadwalAsesmen.tuk', 'dokumenPenilaian.jenisDokumen'])
            ->findOrFail($penilaian);

        return (new PenilaianMukResource($hasil))->detailUntuk('asesor');
    }
}
