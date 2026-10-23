<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;

/**
 * Handler yang perlu tindakan tambahan saat dokumennya selesai (mis. FR.IA.08 membuat dokumen
 * asesmen lanjutan). Dipanggil DokumenWorkflow di dalam transaksi, sebelum sinkron penyelesaian penilaian.
 */
interface SetelahSelesai
{
    public function setelahSelesai(DokumenPenilaian $dokumen): void;
}
