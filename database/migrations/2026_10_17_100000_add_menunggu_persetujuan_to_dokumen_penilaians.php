<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status baru dokumen MUK: "menunggu_persetujuan" — dokumen sudah diisi & ditandatangani
 * pengisi (mis. asesor pada FR.AK.07 / FR.AK.01) dan menunggu tanda tangan persetujuan
 * pihak kedua (asesi). Hanya menambah nilai enum: aman untuk data yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE dokumen_penilaians MODIFY COLUMN status
        ENUM('belum_diisi','draft','menunggu_review','menunggu_persetujuan','selesai') NOT NULL DEFAULT 'belum_diisi'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Dokumen yang sedang menunggu persetujuan dikembalikan ke draft agar enum lama valid.
        DB::table('dokumen_penilaians')->where('status', 'menunggu_persetujuan')->update(['status' => 'draft']);

        DB::statement("ALTER TABLE dokumen_penilaians MODIFY COLUMN status
        ENUM('belum_diisi','draft','menunggu_review','selesai') NOT NULL DEFAULT 'belum_diisi'");
    }
};
