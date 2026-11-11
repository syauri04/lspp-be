<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pendaftaran yang asesmennya SUDAH selesai sebelum fitur penerbitan sertifikat ada
 * ikut masuk antrean (status menunggu_dokumen). Idempoten: hanya yang belum punya baris.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasil = [
            'selesai_kompeten' => 'kompeten',
            'selesai_belum_kompeten' => 'belum_kompeten',
        ];

        DB::table('penilaian_muks as p')
            ->join('pendaftaran_sertifikasis as d', 'd.id', '=', 'p.pendaftaran_sertifikasi_id')
            ->whereIn('p.status', array_keys($hasil))
            ->where('d.status', 'asesmen_selesai')
            ->whereNotExists(fn ($q) => $q->from('penerbitan_sertifikasis as s')->whereColumn('s.pendaftaran_sertifikasi_id', 'd.id'))
            ->orderBy('p.id')
            ->select(['p.id', 'p.status', 'p.pendaftaran_sertifikasi_id'])
            ->chunk(200, function ($baris) use ($hasil) {
                DB::table('penerbitan_sertifikasis')->insert($baris->map(fn ($b) => [
                    'pendaftaran_sertifikasi_id' => $b->pendaftaran_sertifikasi_id,
                    'penilaian_muk_id' => $b->id,
                    'hasil' => $hasil[$b->status],
                    'status' => 'menunggu_dokumen',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });
    }

    public function down(): void
    {
        // Data proses penerbitan tidak dihapus otomatis; tabel dihapus oleh migrasi sebelumnya.
    }
};
