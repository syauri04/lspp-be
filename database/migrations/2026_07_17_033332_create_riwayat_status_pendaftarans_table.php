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
        Schema::create('riwayat_status_pendaftarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_sertifikasi_id')->constrained()->cascadeOnDelete();

            $table->string('status_dari')->nullable();
            $table->string('status_ke');
            $table->text('keterangan')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_status_pendaftarans');
    }
};
