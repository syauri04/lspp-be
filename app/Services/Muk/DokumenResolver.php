<?php

namespace App\Services\Muk;

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\PenilaianMuk;
use App\Services\Muk\Dokumen\DokumenHandler;
use App\Services\Muk\Dokumen\DokumenHandlerRegistry;

/**
 * Mencari dokumen dari URL (penilaian id + slug). Penilaian SELALU lewat scope
 * pemilik; slug harus terdaftar di registry. Gagal = 404.
 */
class DokumenResolver
{
    public function __construct(private DokumenHandlerRegistry $registry) {}

    /** @return array{0: DokumenPenilaian, 1: DokumenHandler} */
    public function temukan(Asesi|Asesor $user, int $penilaianId, string $slug): array
    {
        $handler = $this->registry->untukSlug($slug);
        abort_if($handler === null, 404);

        $penilaian = PenilaianMuk::query()->forUser($user)->findOrFail($penilaianId);

        $dokumen = $penilaian->dokumenPenilaian()
            ->kode($handler->kode())
            ->with('jenisDokumen')
            ->firstOrFail();
        $dokumen->setRelation('penilaianMuk', $penilaian);

        return [$dokumen, $handler];
    }
}
