<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bukti yang relevan per elemen FR.APL.02: tiap baris berisi berkas unggahan,
     * link, atau keduanya. Berkas di disk privat; DB hanya menyimpan path.
     * Kolom lama apl02_jawabans.bukti_relevan tidak lagi dipakai (dibiarkan, aman untuk data lama).
     */
    public function up(): void
    {
        Schema::create('apl02_bukti_relevans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apl02_jawaban_id')->constrained('apl02_jawabans')->cascadeOnDelete();
            $table->string('link', 2048)->nullable();
            $table->string('path_file')->nullable();
            $table->string('nama_file_asli')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('ukuran_file')->nullable(); // KB
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['apl02_jawaban_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apl02_bukti_relevans');
    }
};
