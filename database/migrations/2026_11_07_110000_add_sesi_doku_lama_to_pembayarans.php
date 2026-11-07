<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat sesi DOKU yang direset admin (kode transaksi/invoice lama, URL, nominal, waktu, admin).
 * Webhook tetap mengenali invoice lama sehingga pembayaran dari link lama tidak hilang.
 * Hanya menambah kolom nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayarans', function (Blueprint $table) {
            $table->json('sesi_doku_lama')->nullable()->after('gateway_payload');
        });
    }

    public function down(): void
    {
        Schema::table('pembayarans', function (Blueprint $table) {
            $table->dropColumn('sesi_doku_lama');
        });
    }
};
