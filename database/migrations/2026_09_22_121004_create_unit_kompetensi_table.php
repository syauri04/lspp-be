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
        Schema::create('unit_kompetensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skema_sertifikasi_id')->constrained('skema_sertifikasi')->cascadeOnDelete();
            $table->string('kode_unit'); // ex: N.79KEW00.001.2
            $table->text('judul_unit');
            $table->unsignedInteger('urutan')->default(0); // kolom "No" di dokumen
            $table->timestamps();

            $table->unique(['skema_sertifikasi_id', 'kode_unit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_kompetensi');
    }
};
