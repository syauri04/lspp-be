<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Berkas bukti persyaratan dasar yang diunggah asesi di FR.APL.01.
     * Disimpan di disk privat; DB hanya menyimpan path. Kolom nullable (aman untuk data lama).
     */
    public function up(): void
    {
        Schema::table('apl01_verifikasi_persyaratans', function (Blueprint $table) {
            $table->string('path_file')->nullable()->after('bukti_persyaratan_dasar_id');
            $table->string('nama_file_asli')->nullable()->after('path_file');
            $table->string('mime_type', 100)->nullable()->after('nama_file_asli');
            $table->unsignedInteger('ukuran_file')->nullable()->after('mime_type'); // KB
        });
    }

    public function down(): void
    {
        Schema::table('apl01_verifikasi_persyaratans', function (Blueprint $table) {
            $table->dropColumn(['path_file', 'nama_file_asli', 'mime_type', 'ukuran_file']);
        });
    }
};
