<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Template perangkat "umum" (berlaku semua metode) untuk FR.IA.06A/B/C:
 * - mapa_templates.metode menerima 'umum' (soal esai, kunci jawaban & waktu di kolom instrumen),
 * - bagian penandatangan 'penyusun_ia06b' (Penyusun & Validator FR.IA.06B).
 * Hanya menambah nilai enum: aman untuk data yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE mapa_templates MODIFY COLUMN metode ENUM('kt','tpd','vp','umum') NOT NULL");
        DB::statement("ALTER TABLE mapa_template_penandatangans MODIFY COLUMN bagian
        ENUM('konfirmasi','penyusun_mapa01','penyusun_mapa02','penyusun_ia02','penyusun_ia06b') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('mapa_template_penandatangans')->where('bagian', 'penyusun_ia06b')->delete();
        DB::table('mapa_templates')->where('metode', 'umum')->delete();
        DB::statement("ALTER TABLE mapa_template_penandatangans MODIFY COLUMN bagian
        ENUM('konfirmasi','penyusun_mapa01','penyusun_mapa02','penyusun_ia02') NOT NULL");
        DB::statement("ALTER TABLE mapa_templates MODIFY COLUMN metode ENUM('kt','tpd','vp') NOT NULL");
    }
};
