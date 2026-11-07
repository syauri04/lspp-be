<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1. asesis.nonaktif_at: akun asesi bisa dinonaktifkan (tidak bisa login) tanpa menghapus data.
 * 2. FK pendaftaran_sertifikasis.asesi_id: cascade -> restrict, agar menghapus asesi tidak lagi
 *    ikut menghapus pendaftaran, pembayaran, jadwal, dan dokumen MUK-nya.
 * Hanya menambah kolom nullable & mengganti aturan FK; tidak ada data yang diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asesis', function (Blueprint $table) {
            $table->timestamp('nonaktif_at')->nullable()->after('email_verified_at');
        });

        Schema::table('pendaftaran_sertifikasis', function (Blueprint $table) {
            $table->dropForeign(['asesi_id']);
            $table->foreign('asesi_id')->references('id')->on('asesis')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pendaftaran_sertifikasis', function (Blueprint $table) {
            $table->dropForeign(['asesi_id']);
            $table->foreign('asesi_id')->references('id')->on('asesis')->cascadeOnDelete();
        });

        Schema::table('asesis', function (Blueprint $table) {
            $table->dropColumn('nonaktif_at');
        });
    }
};
