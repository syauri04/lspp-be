<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayarans', function (Blueprint $table) {
            $table->renameColumn('midtrans_transaction_id', 'external_transaction_id');
            $table->renameColumn('midtrans_payload', 'gateway_payload');
        });
    }

    public function down(): void
    {
        Schema::table('pembayarans', function (Blueprint $table) {
            $table->renameColumn('external_transaction_id', 'midtrans_transaction_id');
            $table->renameColumn('gateway_payload', 'midtrans_payload');
        });
    }
};
