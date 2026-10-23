<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;

/**
 * Pola dokumen "disiapkan peninjau -> dijawab pengisi -> dinilai peninjau" (mis. FR.IA.03).
 *
 * Alur status (DokumenWorkflow):
 * belum_diisi (persiapan peninjau) -> buka -> (pengisi menjawab: draft) -> submit
 * -> menunggu_review -> nilaiAkhir (penilaian + ttd peninjau) -> selesai.
 * Isian pengisi di `data`, persiapan & penilaian peninjau di `data_review`.
 */
interface DisiapkanDanDinilai extends DisiapkanPeninjau
{
    /** Aturan validasi penilaian peninjau (key `penilaian`). */
    public function rulesPenilaian(DokumenPenilaian $dokumen): array;

    /** Simpan penilaian; true bila berubah. */
    public function simpanPenilaian(DokumenPenilaian $dokumen, array $validated): bool;

    /** Kelengkapan penilaian sebelum diselesaikan ([field => pesan]). */
    public function kesalahanPenilaian(DokumenPenilaian $dokumen): array;
}
