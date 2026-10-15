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
        Schema::create('apl01_verifikasi_persyaratans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokumen_penilaian_id')->constrained('dokumen_penilaians')->cascadeOnDelete();
            $table->foreignId('bukti_persyaratan_dasar_id')->constrained('bukti_persyaratan_dasars')->cascadeOnDelete();
            $table->boolean('ada')->nullable();
            $table->boolean('memenuhi_syarat')->nullable();
            $table->timestamps();

            $table->unique(['dokumen_penilaian_id', 'bukti_persyaratan_dasar_id'], 'apl01_verifikasi_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apl01_verifikasi_persyaratans');
    }
};
