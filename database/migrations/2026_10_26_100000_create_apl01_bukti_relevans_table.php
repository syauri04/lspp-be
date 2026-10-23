<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lampiran "bukti yang relevan" FR.APL.01 (dipakai sebagai bukti portofolio FR.IA.08/IA.09 metode VP).
     * Baris dari template VP (CMS) punya `kunci` (sha1 nama) dan tidak dapat dihapus asesi; baris tambahan
     * asesi `kunci` = null. Tiap baris berisi berkas unggahan, link, atau keduanya (berkas di disk privat).
     * Tabel baru, tidak menyentuh data lama.
     */
    public function up(): void
    {
        Schema::create('apl01_bukti_relevans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokumen_penilaian_id')->constrained('dokumen_penilaians')->cascadeOnDelete();
            $table->char('kunci', 40)->nullable();
            $table->string('nama', 500);
            $table->string('link', 2048)->nullable();
            $table->string('path_file')->nullable();
            $table->string('nama_file_asli')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('ukuran_file')->nullable(); // KB
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();

            // Satu baris per butir template per dokumen (NULL = tambahan asesi, boleh banyak).
            $table->unique(['dokumen_penilaian_id', 'kunci']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apl01_bukti_relevans');
    }
};
