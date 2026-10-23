<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Template per skema + metode diperluas menjadi "perangkat asesmen":
 * - mapa_templates.instrumen (JSON): isi instrumen IA per kode dokumen, mis.
 *   { "FR.IA.02": { skenario, waktu }, "FR.IA.03": { pertanyaan: [..] } }.
 * - bagian penandatangan baru "penyusun_ia02" (Penyusun & Validator FR.IA.02).
 * Hanya menambah kolom nullable & nilai enum: aman untuk data yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapa_templates', function (Blueprint $table) {
            $table->json('instrumen')->nullable()->after('metode');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mapa_template_penandatangans MODIFY COLUMN bagian
            ENUM('konfirmasi','penyusun_mapa01','penyusun_mapa02','penyusun_ia02') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('mapa_template_penandatangans')->where('bagian', 'penyusun_ia02')->delete();
            DB::statement("ALTER TABLE mapa_template_penandatangans MODIFY COLUMN bagian
            ENUM('konfirmasi','penyusun_mapa01','penyusun_mapa02') NOT NULL");
        }

        Schema::table('mapa_templates', function (Blueprint $table) {
            $table->dropColumn('instrumen');
        });
    }
};
