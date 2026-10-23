<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bagian penandatangan baru "penyusun_ia04a" & "penyusun_ia04b" (Penyusun & Validator FR.IA.04A/B,
 * template metode KT). Isi instrumen IA.04A/B disimpan di mapa_templates.instrumen (kolom sudah ada).
 * Hanya menambah nilai enum: aman untuk data yang sudah ada.
 */
return new class extends Migration
{
    private const LAMA = "'konfirmasi','penyusun_mapa01','penyusun_mapa02','penyusun_ia02','penyusun_ia06b','penyusun_ia09'";

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE mapa_template_penandatangans MODIFY COLUMN bagian
        ENUM('.self::LAMA.",'penyusun_ia04a','penyusun_ia04b') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('mapa_template_penandatangans')->whereIn('bagian', ['penyusun_ia04a', 'penyusun_ia04b'])->delete();
        DB::statement('ALTER TABLE mapa_template_penandatangans MODIFY COLUMN bagian ENUM('.self::LAMA.') NOT NULL');
    }
};
