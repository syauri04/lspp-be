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
        Schema::create('kriteria_unjuk_kerjas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('elemen_kompetensi_id')->constrained('elemen_kompetensis')->cascadeOnDelete();
            $table->string('nomor_kuk');
            $table->text('deskripsi');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kriteria_unjuk_kerjas');
    }
};
