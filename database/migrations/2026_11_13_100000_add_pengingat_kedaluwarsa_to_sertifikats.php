<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda pengingat "e-sertifikat akan kedaluwarsa" sudah dikirim (agar tiap sertifikat hanya
 * diingatkan sekali; dikosongkan lagi bila tanggal kedaluwarsa diubah) + indeks pencarian harian.
 * Aman untuk data lama: kolom nullable, tanpa mengubah baris yang ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sertifikats', function (Blueprint $table) {
            $table->timestamp('pengingat_kedaluwarsa_at')->nullable()->after('alasan_status');
            $table->index(['status', 'tanggal_expired'], 'sertifikats_status_expired_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sertifikats', function (Blueprint $table) {
            $table->dropIndex('sertifikats_status_expired_idx');
            $table->dropColumn('pengingat_kedaluwarsa_at');
        });
    }
};
