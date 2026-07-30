<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dokumen_pendaftarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_sertifikasi_id')->constrained()->cascadeOnDelete();

            $table->enum('jenis_dokumen', ['ktp', 'ijazah', 'portfolio', 'pas_foto', 'cv']);
            $table->string('nama_file_asli');
            $table->string('path_file');      // path di disk PRIVATE, bukan public/
            $table->string('mime_type', 50);
            $table->unsignedInteger('ukuran_file'); // dalam KB

            $table->timestamps();

            $table->unique(['pendaftaran_sertifikasi_id', 'jenis_dokumen'], 'dokumen_pendaftaran_jenis_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumen_pendaftarans');
    }
};
