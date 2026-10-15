<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - snapshot: salinan data master (skema, unit, elemen, bukti) saat dokumen disubmit,
     *   supaya isi dokumen yang sudah ditandatangani tidak berubah bila skema diedit di CMS.
     * - riwayat_dokumen_penilaians: audit trail setiap aksi (simpan, tanda tangan, submit,
     *   review, dikembalikan), dipakai generik untuk semua jenis dokumen MUK.
     *
     * Aman untuk data lama: kolom baru nullable, tabel baru.
     */
    public function up(): void
    {
        Schema::table('dokumen_penilaians', function (Blueprint $table) {
            $table->json('snapshot')->nullable()->after('data');
        });

        Schema::create('riwayat_dokumen_penilaians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokumen_penilaian_id')->constrained('dokumen_penilaians')->cascadeOnDelete();
            $table->string('aksi', 50);
            $table->string('status_dari', 30)->nullable();
            $table->string('status_ke', 30)->nullable();
            $table->nullableMorphs('pelaku'); // Asesi / Asesor / User (admin)
            $table->text('catatan')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['dokumen_penilaian_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_dokumen_penilaians');

        Schema::table('dokumen_penilaians', function (Blueprint $table) {
            $table->dropColumn('snapshot');
        });
    }
};
