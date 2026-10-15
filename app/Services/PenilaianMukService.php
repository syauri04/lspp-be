<?php

namespace App\Services;

use App\Models\DokumenPenilaian;
use App\Models\JadwalAsesmen;
use App\Models\JenisDokumenMuk;
use App\Models\PenilaianMuk;

class PenilaianMukService
{
    /** Dipanggil saat admin menjadwalkan asesmen. APL.01 & APL.02 wajib di semua metode. */
    public function mulaiDariJadwal(JadwalAsesmen $jadwal): PenilaianMuk
    {
        // updateOrCreate: kalau jadwal diganti, penilaian yang sama cukup dipindah ke jadwal baru
        $penilaian = PenilaianMuk::updateOrCreate(
            ['pendaftaran_sertifikasi_id' => $jadwal->pendaftaran_sertifikasi_id],
            ['jadwal_asesmen_id' => $jadwal->id]
        );

        $jenisIds = JenisDokumenMuk::whereIn('kode', ['FR.APL.01', 'FR.APL.02'])->pluck('id');

        foreach ($jenisIds as $jenisId) {
            DokumenPenilaian::firstOrCreate(
                ['penilaian_muk_id' => $penilaian->id, 'jenis_dokumen_muk_id' => $jenisId],
                ['status' => 'belum_diisi']
            );
        }

        return $penilaian;
    }
}
