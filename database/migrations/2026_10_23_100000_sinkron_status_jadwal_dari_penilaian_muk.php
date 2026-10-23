<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill: selaraskan status jadwal asesmen & pendaftaran dengan penilaian MUK yang sudah ada
 * (sebelum sinkronisasi otomatis dibuat). Hanya memajukan status, tidak pernah memundurkan:
 * - penilaian "berlangsung"            -> jadwal "terjadwal" menjadi "berlangsung",
 * - penilaian "selesai_(belum_)kompeten" -> jadwal "selesai", pendaftaran "dijadwalkan" -> "asesmen_selesai".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('penilaian_muks')->orderBy('id')->each(function ($p) {
            if ($p->status === 'berlangsung') {
                DB::table('jadwal_asesmens')->where('id', $p->jadwal_asesmen_id)->where('status', 'terjadwal')
                    ->update(['status' => 'berlangsung', 'updated_at' => now()]);

                return;
            }
            if (! in_array($p->status, ['selesai_kompeten', 'selesai_belum_kompeten'], true)) {
                return;
            }

            DB::table('jadwal_asesmens')->where('id', $p->jadwal_asesmen_id)->whereIn('status', ['terjadwal', 'berlangsung'])
                ->update(['status' => 'selesai', 'updated_at' => now()]);

            $berubah = DB::table('pendaftaran_sertifikasis')->where('id', $p->pendaftaran_sertifikasi_id)->where('status', 'dijadwalkan')
                ->update(['status' => 'asesmen_selesai', 'updated_at' => now()]);
            if ($berubah) {
                DB::table('riwayat_status_pendaftarans')->insert([
                    'pendaftaran_sertifikasi_id' => $p->pendaftaran_sertifikasi_id,
                    'status_dari' => 'dijadwalkan',
                    'status_ke' => 'asesmen_selesai',
                    'keterangan' => 'Asesmen selesai di MUK dengan hasil: '.($p->status === 'selesai_kompeten' ? 'Kompeten' : 'Belum Kompeten').'.',
                    'changed_by' => null,
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Backfill data: tidak dibalik.
    }
};
