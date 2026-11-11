<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bukti relevan FR.APL.02 dapat merujuk "Lampiran bukti yang relevan" FR.APL.01 (bagian 3) milik penilaian
 * yang sama, selain unggah berkas / link. Hanya referensi (tanpa menyalin berkas); bila lampiran APL.01
 * dihapus, rujukan menjadi NULL. Hanya menambah kolom nullable; data lama tetap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apl02_bukti_relevans', function (Blueprint $table) {
            $table->foreignId('apl01_bukti_relevan_id')->nullable()->after('apl02_jawaban_id')
                ->constrained('apl01_bukti_relevans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('apl02_bukti_relevans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('apl01_bukti_relevan_id');
        });
    }
};
