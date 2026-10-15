<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE dokumen_penilaians MODIFY COLUMN status
        ENUM('belum_diisi','draft','menunggu_review','selesai') NOT NULL DEFAULT 'belum_diisi'");

        DB::statement("ALTER TABLE penilaian_muks MODIFY COLUMN status
        ENUM('menunggu_pengisian_apl','menunggu_review','berlangsung','selesai_kompeten','selesai_belum_kompeten')
        NOT NULL DEFAULT 'menunggu_pengisian_apl'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE dokumen_penilaians MODIFY COLUMN status
        ENUM('belum_diisi','draft','selesai') NOT NULL DEFAULT 'belum_diisi'");

        DB::statement("ALTER TABLE penilaian_muks MODIFY COLUMN status
        ENUM('menunggu_pengisian_apl','menunggu_review_admin','menunggu_review_asesor','berlangsung','selesai_kompeten','selesai_belum_kompeten')
        NOT NULL DEFAULT 'menunggu_pengisian_apl'");
    }
};
