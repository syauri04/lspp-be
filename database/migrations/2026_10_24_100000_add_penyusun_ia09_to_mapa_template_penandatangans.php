<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bagian penandatangan baru "penyusun_ia09" (Penyusun & Validator FR.IA.09, template metode VP).
 * Pertanyaan wawancara IA.09 disimpan di mapa_templates.instrumen (kolom sudah ada).
 * Hanya menambah nilai enum: aman untuk data yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE mapa_template_penandatangans MODIFY COLUMN bagian
        ENUM('konfirmasi','penyusun_mapa01','penyusun_mapa02','penyusun_ia02','penyusun_ia06b','penyusun_ia09') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('mapa_template_penandatangans')->where('bagian', 'penyusun_ia09')->delete();
        DB::statement("ALTER TABLE mapa_template_penandatangans MODIFY COLUMN bagian
        ENUM('konfirmasi','penyusun_mapa01','penyusun_mapa02','penyusun_ia02','penyusun_ia06b') NOT NULL");
    }
};
