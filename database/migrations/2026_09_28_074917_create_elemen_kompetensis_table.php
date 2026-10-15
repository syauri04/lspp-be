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
        Schema::create('elemen_kompetensis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kompetensi_id')->constrained('unit_kompetensi')->cascadeOnDelete();
            $table->unsignedInteger('nomor_elemen');
            $table->text('nama_elemen');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('elemen_kompetensis');
    }
};
