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
        Schema::create('jadwal_asesmens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_sertifikasi_id')->unique()->constrained('pendaftaran_sertifikasis')->cascadeOnDelete();
            $table->foreignId('asesor_id')->constrained('asesors');
            $table->foreignId('tuk_id')->constrained('t_u_k_s'); // eksplisit, nama tabel nggak konvensional
            $table->date('tanggal_asesmen');
            $table->time('waktu_mulai');
            $table->time('waktu_selesai')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('dijadwalkan_by')->constrained('users');
            $table->enum('status', ['terjadwal', 'berlangsung', 'selesai', 'dibatalkan'])->default('terjadwal');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal_asesmens');
    }
};
