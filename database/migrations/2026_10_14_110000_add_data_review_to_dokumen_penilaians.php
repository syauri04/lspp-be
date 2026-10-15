<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data isian peninjau (admin LSP / asesor) yang tidak punya kolom khusus,
     * mis. verifikasi bukti administratif FR.APL.01. Dipisah dari `data` (isian pengisi)
     * agar isian asesi dan hasil tinjauan tidak saling menimpa. Nullable, aman untuk data lama.
     */
    public function up(): void
    {
        Schema::table('dokumen_penilaians', function (Blueprint $table) {
            $table->json('data_review')->nullable()->after('snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('dokumen_penilaians', function (Blueprint $table) {
            $table->dropColumn('data_review');
        });
    }
};
