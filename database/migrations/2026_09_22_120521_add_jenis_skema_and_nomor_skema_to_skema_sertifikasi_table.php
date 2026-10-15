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
        Schema::table('skema_sertifikasi', function (Blueprint $table) {
            $table->enum('jenis_skema', ['KKNI', 'Okupasi', 'Klaster'])->nullable()->after('title');
            $table->string('nomor_skema')->nullable()->unique()->after('jenis_skema');
            $table->longText('standar_kompetensi_kerja')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skema_sertifikasi', function (Blueprint $table) {
            $table->dropColumn(['jenis_skema', 'nomor_skema', 'standar_kompetensi_kerja']);
        });
    }
};
