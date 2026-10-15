<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Metode asesmen yang ditentukan asesor setelah FR.APL.02 "dapat dilanjutkan".
     * Nullable (aman untuk data lama). Dokumen turunan metode (MAPA, dst) dibuat di fase berikutnya.
     */
    public function up(): void
    {
        Schema::table('penilaian_muks', function (Blueprint $table) {
            $table->enum('metode_asesmen', ['kt', 'tpd', 'vp'])->nullable()->after('ditentukan_oleh_asesor_id');
        });
    }

    public function down(): void
    {
        Schema::table('penilaian_muks', function (Blueprint $table) {
            $table->dropColumn('metode_asesmen');
        });
    }
};
