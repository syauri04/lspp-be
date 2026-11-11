<?php

namespace App\Http\Controllers\API\Sertifikat;

use App\Http\Controllers\Controller;
use App\Http\Resources\Sertifikat\SertifikatPublikResource;
use App\Models\PenerbitanSertifikasi;
use App\Models\Sertifikat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/sertifikat/verifikasi?nomor=... (publik, dibatasi per IP).
 * Cocok PERSIS dengan nomor blanko ATAU nomor sertifikat (setelah normalisasi spasi & huruf besar):
 * tidak ada pencarian sebagian / daftar, sehingga data tidak dapat dikumpulkan massal.
 * Hanya sertifikat yang prosesnya sudah diselesaikan super admin.
 */
class VerifikasiSertifikatController extends Controller
{
    public function __invoke(Request $request): SertifikatPublikResource|JsonResponse
    {
        $validated = $request->validate(['nomor' => ['required', 'string', 'max:100']]);
        $nomor = Sertifikat::normalisasi($validated['nomor']);

        $sertifikat = $nomor === '' ? null : Sertifikat::query()
            ->where(fn ($q) => $q->where('no_blanko_normal', $nomor)->orWhere('no_sertifikat_normal', $nomor))
            ->whereHas('penerbitan', fn ($q) => $q->where('status', PenerbitanSertifikasi::SELESAI))
            ->first();

        if (! $sertifikat) {
            return response()->json(['message' => 'Sertifikat tidak ditemukan.'], 404);
        }

        return new SertifikatPublikResource($sertifikat);
    }
}
