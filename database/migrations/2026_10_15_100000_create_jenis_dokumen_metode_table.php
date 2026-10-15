<?php

use Database\Seeders\JenisDokumenMukSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dokumen MUK per metode asesmen (KT / TPD / VP). Dipakai saat asesor menetapkan metode
     * untuk membuat dokumen turunan (MAPA, AK, IA). Sekaligus mengisi katalog 22 jenis dokumen
     * (upsert, aman untuk data lama: APL.01/02 yang sudah ada tetap memakai ID yang sama).
     */
    public function up(): void
    {
        Schema::create('jenis_dokumen_metode', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_dokumen_muk_id')->constrained('jenis_dokumen_muks')->cascadeOnDelete();
            $table->enum('metode', ['kt', 'tpd', 'vp']);
            $table->unsignedInteger('urutan')->default(0);

            $table->unique(['jenis_dokumen_muk_id', 'metode']);
        });

        (new JenisDokumenMukSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_dokumen_metode');
        // Katalog jenis dokumen sengaja tidak dihapus (bisa sudah dirujuk dokumen_penilaians).
    }
};
