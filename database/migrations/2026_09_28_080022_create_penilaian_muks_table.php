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
        Schema::create('penilaian_muks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_sertifikasi_id')->unique()->constrained('pendaftaran_sertifikasis')->cascadeOnDelete();
            $table->foreignId('jadwal_asesmen_id')->nullable()->constrained('jadwal_asesmens')->nullOnDelete();
            $table->foreignId('ditentukan_oleh_asesor_id')->nullable()->constrained('asesors');
            $table->enum('status', [
                'menunggu_pengisian_apl',
                'menunggu_review_admin',
                'menunggu_review_asesor',
                'berlangsung',
                'selesai_kompeten',
                'selesai_belum_kompeten',
            ])->default('menunggu_pengisian_apl');
            $table->timestamp('metode_ditentukan_at')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penilaian_muks');
    }
};
