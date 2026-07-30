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
        Schema::create('pendaftaran_sertifikasis', function (Blueprint $table) {
            $table->id();
            $table->uuid('kode_pendaftaran')->unique();

            $table->foreignId('asesi_id')->constrained('asesis')->cascadeOnDelete();
            $table->foreignId('skema_sertifikasi_id')->constrained('skema_sertifikasi')->restrictOnDelete();

            $table->enum('status', [
                'submitted',          // baru submit, menunggu review admin
                'approved',           // dokumen sesuai, lanjut ke pembayaran
                'rejected',           // dokumen ditolak
                'awaiting_payment',   // link checkout sudah dikirim, menunggu bayar
                'paid',               // pembayaran sukses (dari webhook midtrans)
                'completed',          // seluruh proses sertifikasi selesai
                'expired',            // batas waktu bayar lewat
                'cancelled',          // dibatalkan asesi/admin
            ])->default('submitted');

            $table->text('catatan_admin')->nullable(); // alasan reject / catatan review
            $table->decimal('harga_snapshot', 12, 2);  // snapshot harga skema saat daftar (harga skema bisa berubah nanti)

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['asesi_id', 'status']);
            $table->index(['skema_sertifikasi_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pendaftaran_sertifikasis');
    }
};
