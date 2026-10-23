<?php

namespace App\Services\Muk;

use App\Models\PenilaianMuk;

/**
 * Menyelaraskan status di luar MUK (jadwal asesmen & pendaftaran sertifikasi, dipakai CMS
 * dan website utama) dengan progres penilaian MUK:
 * - metode asesmen ditetapkan  -> jadwal "berlangsung",
 * - penilaian selesai (K/BK)   -> jadwal "selesai", pendaftaran "asesmen_selesai" (+ riwayat).
 * Dipanggil di dalam transaksi pemanggil; idempoten (hanya maju, tidak mundur).
 */
class SinkronStatusAsesmen
{
    public function mulai(PenilaianMuk $penilaian): void
    {
        $penilaian->jadwalAsesmen()->where('status', 'terjadwal')->update(['status' => 'berlangsung']);
    }

    public function selesai(PenilaianMuk $penilaian): void
    {
        $penilaian->jadwalAsesmen()->whereIn('status', ['terjadwal', 'berlangsung'])->update(['status' => 'selesai']);

        $pendaftaran = $penilaian->pendaftaran()->lockForUpdate()->first();
        if (! $pendaftaran || $pendaftaran->status !== 'dijadwalkan') {
            return;
        }

        $hasil = $penilaian->status === PenilaianMuk::STATUS_SELESAI_KOMPETEN ? 'Kompeten' : 'Belum Kompeten';
        $pendaftaran->update(['status' => 'asesmen_selesai']);
        $pendaftaran->riwayatStatus()->create([
            'status_dari' => 'dijadwalkan',
            'status_ke' => 'asesmen_selesai',
            'keterangan' => "Asesmen selesai di MUK dengan hasil: {$hasil}.",
            // Diputuskan asesor (bukan user CMS) -> changed_by kosong.
            'changed_by' => null,
        ]);
    }
}
