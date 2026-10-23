<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use Illuminate\Support\Collection;

/**
 * Handler dengan aturan akses tambahan per peran (mis. FR.IA.06B hanya untuk asesor,
 * FR.IA.06C terbuka setelah asesi mengirim FR.IA.06A). Dicek AksesDokumen sebelum aturan umum.
 */
interface PunyaPrasyarat
{
    /**
     * @param  Collection<int, DokumenPenilaian>  $semuaDokumen  (jenisDokumen sudah di-load)
     * @return string|null alasan terkunci, atau null bila boleh
     */
    public function alasanPrasyarat(DokumenPenilaian $dokumen, Collection $semuaDokumen, string $peran): ?string;
}
