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
        Schema::create('pembayarans', function (Blueprint $table) {
            $table->id();
            $table->string('kode_transaksi')->unique(); // dipakai sebagai order_id ke Midtrans

            $table->foreignId('pendaftaran_sertifikasi_id')->constrained()->cascadeOnDelete();

            $table->decimal('jumlah', 12, 2);
            $table->string('metode_pembayaran')->nullable(); // terisi setelah user pilih (va, gopay, dll)

            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'cancelled', 'refunded'])
                ->default('pending');

            $table->string('midtrans_transaction_id')->nullable();
            $table->json('midtrans_payload')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();

            $table->index(['pendaftaran_sertifikasi_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayarans');
    }
};
