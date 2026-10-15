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
        Schema::create('dokumen_penilaians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penilaian_muk_id')->constrained('penilaian_muks')->cascadeOnDelete();
            $table->foreignId('jenis_dokumen_muk_id')->constrained('jenis_dokumen_muks');
            $table->enum('status', ['belum_diisi', 'draft', 'selesai'])->default('belum_diisi');
            $table->json('data')->nullable();

            $table->string('path_tanda_tangan_asesi')->nullable();
            $table->date('tanggal_tanda_tangan_asesi')->nullable();

            $table->enum('hasil_rekomendasi_admin', ['diterima', 'ditolak'])->nullable();
            $table->text('catatan_admin')->nullable();
            $table->foreignId('direview_oleh_user_id')->nullable()->constrained('users');
            $table->string('path_tanda_tangan_admin')->nullable();
            $table->date('tanggal_tanda_tangan_admin')->nullable();

            $table->enum('hasil_rekomendasi_asesor', ['dapat_dilanjutkan', 'tidak_dapat_dilanjutkan'])->nullable();
            $table->text('catatan_asesor')->nullable();
            $table->foreignId('ditinjau_oleh_asesor_id')->nullable()->constrained('asesors');
            $table->string('path_tanda_tangan_asesor')->nullable();
            $table->date('tanggal_tanda_tangan_asesor')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['penilaian_muk_id', 'jenis_dokumen_muk_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumen_penilaians');
    }
};
