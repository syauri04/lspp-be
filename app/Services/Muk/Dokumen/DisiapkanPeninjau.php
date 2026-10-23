<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;

/**
 * Dokumen yang DISIAPKAN dulu oleh pihak lain (penyiap, mis. asesor) sebelum dijawab pengisi
 * (mis. FR.IA.03, FR.IA.06A). Selama belum dibuka, pengisi tidak dapat melihat dokumen.
 * Persiapan disimpan di data_review.persiapan.
 */
interface DisiapkanPeninjau
{
    /** Peran yang menyiapkan & membuka dokumen (mis. 'asesor'). */
    public function penyiap(): string;

    /** Sudah dibuka untuk pengisi? */
    public function sudahDibuka(DokumenPenilaian $dokumen): bool;

    /** Aturan validasi PATCH persiapan (key `persiapan`). */
    public function rulesPersiapan(DokumenPenilaian $dokumen): array;

    /** Simpan persiapan; true bila berubah. */
    public function simpanPersiapan(DokumenPenilaian $dokumen, array $validated): bool;

    /** Kelengkapan sebelum dibuka ([field => pesan]). */
    public function kesalahanBuka(DokumenPenilaian $dokumen): array;

    /** Tandai dibuka (mis. bekukan daftar pertanyaan). */
    public function buka(DokumenPenilaian $dokumen): void;
}
