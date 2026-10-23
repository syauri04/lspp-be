<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;

/**
 * Handler dokumen yang menampilkan penandatangan template CMS (penyusun, validator,
 * konfirmasi) — gambar tanda tangannya dilayani lewat endpoint .../penandatangan/{id}.
 */
interface PunyaPenandatangan
{
    /** Path gambar ttd penandatangan (salinan snapshot bila dokumen sudah dikirim), null bila tidak ada. */
    public function pathTtdPenandatangan(DokumenPenilaian $dokumen, int $id): ?string;
}
