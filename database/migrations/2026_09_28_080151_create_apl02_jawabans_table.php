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
        Schema::create('apl02_jawabans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokumen_penilaian_id')->constrained('dokumen_penilaians')->cascadeOnDelete();
            $table->foreignId('elemen_kompetensi_id')->constrained('elemen_kompetensis');
            $table->enum('kompeten', ['K', 'BK'])->nullable();
            $table->text('bukti_relevan')->nullable();
            $table->timestamps();

            $table->unique(['dokumen_penilaian_id', 'elemen_kompetensi_id'], 'apl02_jawaban_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apl02_jawabans');
    }
};
