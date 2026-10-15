<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Template FR.MAPA.01 / FR.MAPA.02 per skema + metode, diisi admin di CMS:
     * - rencana asesmen per unit (§2: bukti, jenis bukti L/TL/T, metode & perangkat),
     * - penandatangan: konfirmasi orang yang relevan, penyusun & validator MAPA.01 dan MAPA.02.
     * Tanda tangan berupa gambar unggahan di disk privat (hanya path di DB).
     */
    public function up(): void
    {
        Schema::create('mapa_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skema_sertifikasi_id')->constrained('skema_sertifikasi')->cascadeOnDelete();
            $table->enum('metode', ['kt', 'tpd', 'vp']);
            $table->timestamps();

            $table->unique(['skema_sertifikasi_id', 'metode'], 'mapa_tpl_skema_metode_unique');
        });

        Schema::create('mapa_template_rencanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapa_template_id')->constrained('mapa_templates')->cascadeOnDelete();
            $table->foreignId('unit_kompetensi_id')->constrained('unit_kompetensi')->cascadeOnDelete();
            $table->text('bukti')->nullable();
            $table->boolean('jenis_l')->default(false);
            $table->boolean('jenis_tl')->default(false);
            $table->boolean('jenis_t')->default(false);
            $table->string('observasi_langsung', 100)->nullable();
            $table->string('kegiatan_terstruktur', 100)->nullable();
            $table->string('tanya_jawab', 100)->nullable();
            $table->string('verifikasi_portofolio', 100)->nullable();
            $table->string('reviu_produk', 100)->nullable();
            $table->string('verifikasi_pihak_ketiga', 100)->nullable();
            $table->timestamps();

            $table->unique(['mapa_template_id', 'unit_kompetensi_id'], 'mapa_tpl_rencana_unit_unique');
        });

        Schema::create('mapa_template_penandatangans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapa_template_id')->constrained('mapa_templates')->cascadeOnDelete();
            // konfirmasi = "Konfirmasi dengan orang yang relevan" (MAPA.01);
            // penyusun_mapa01 / penyusun_mapa02 = tabel Penyusun & Validator tiap dokumen.
            $table->enum('bagian', ['konfirmasi', 'penyusun_mapa01', 'penyusun_mapa02']);
            $table->string('peran'); // mis. "Manajer sertifikasi LSP", "penyusun", "validator"
            $table->string('nama')->nullable();
            $table->string('no_reg', 100)->nullable();
            $table->string('path_ttd')->nullable();
            $table->date('tanggal')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['mapa_template_id', 'bagian', 'urutan'], 'mapa_tpl_ttd_bagian_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapa_template_penandatangans');
        Schema::dropIfExists('mapa_template_rencanas');
        Schema::dropIfExists('mapa_templates');
    }
};
