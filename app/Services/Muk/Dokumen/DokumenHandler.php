<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;

/**
 * Kontrak per jenis dokumen MUK (FR.APL.01, FR.APL.02, nanti MAPA/AK/IA).
 * Menambah dokumen baru = membuat handler baru + mendaftarkannya di config('muk.dokumen_handlers').
 *
 * Handler hanya berisi aturan ISI dokumen. Transisi status, tanda tangan, dan
 * audit trail dipusatkan di DokumenWorkflow agar sama untuk semua dokumen.
 */
interface DokumenHandler
{
    /** Kode di jenis_dokumen_muks, mis. "FR.APL.01". */
    public function kode(): string;

    /** Peran yang mengisi dokumen ini (mis. 'asesi'). */
    public function pengisi(): string;

    /** Peran yang meninjau & menandatangani keputusan ("admin" / "asesor"), null bila tidak ada review. */
    public function peninjau(): ?string;

    /**
     * Peran yang menyetujui dokumen dengan tanda tangan setelah pengisi mengirim (mis. asesi pada
     * FR.AK.07 / FR.AK.01), null bila tidak ada. Tidak bisa digabung dengan peninjau.
     */
    public function penyetuju(): ?string;

    /** Apakah pengisi wajib menandatangani sebelum mengirim (MAPA: tidak). */
    public function butuhTandaTangan(): bool;

    /** Payload GET untuk peran tsb (master + isian tersimpan / prefill). */
    public function payload(DokumenPenilaian $dokumen, string $peran): array;

    /** Aturan validasi PATCH (simpan draft, semua parsial). Key eksplisit, tanpa key bebas. */
    public function rulesSimpan(DokumenPenilaian $dokumen): array;

    /**
     * Simpan isian yang sudah tervalidasi. Kembalikan true bila ada perubahan isi.
     * Berkas yang harus dihapus ditambahkan ke $hapusNanti sebagai [disk, path] (dihapus setelah commit).
     */
    public function simpan(DokumenPenilaian $dokumen, array $validated, array &$hapusNanti): bool;

    /** Cek kelengkapan sebelum submit. Kembalikan [field => pesan] (kosong = lengkap). */
    public function kesalahanSubmit(DokumenPenilaian $dokumen): array;

    /** Salinan data master saat submit (disimpan di dokumen_penilaians.snapshot). */
    public function snapshot(DokumenPenilaian $dokumen): array;
}
