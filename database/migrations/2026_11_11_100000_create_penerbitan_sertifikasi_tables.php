<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penerbitan sertifikat setelah asesmen MUK selesai:
 * - penerbitan_sertifikasis        : satu proses per pendaftaran (status & hasil K/BK),
 * - dokumen_sertifikasis           : berkas SK no. 1-4 (satu per jenis),
 * - sertifikats                    : e-sertifikat (no. 5, hanya Kompeten) + nomor & masa berlaku,
 * - riwayat_penerbitan_sertifikasis: jejak audit setiap aksi super admin.
 * Hanya tabel baru: aman untuk data yang sudah ada. Kolom waktu memakai dateTime
 * (bukan timestamp) agar aman di MySQL hosting (explicit_defaults_for_timestamp=OFF).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penerbitan_sertifikasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_sertifikasi_id')->unique()
                ->constrained('pendaftaran_sertifikasis')->restrictOnDelete();
            $table->foreignId('penilaian_muk_id')->nullable()
                ->constrained('penilaian_muks')->nullOnDelete();
            $table->string('hasil', 20); // kompeten | belum_kompeten
            $table->string('status', 30)->index(); // menunggu_dokumen | siap_diselesaikan | selesai
            $table->foreignId('diselesaikan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('diselesaikan_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dokumen_sertifikasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerbitan_sertifikasi_id')->constrained('penerbitan_sertifikasis')->cascadeOnDelete();
            $table->string('jenis', 40);
            $table->string('path_file');
            $table->string('nama_file_asli');
            $table->unsignedInteger('ukuran_file'); // KB
            $table->foreignId('diunggah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['penerbitan_sertifikasi_id', 'jenis']);
        });

        Schema::create('sertifikats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerbitan_sertifikasi_id')->unique()
                ->constrained('penerbitan_sertifikasis')->cascadeOnDelete();
            // Salinan saat terbit: perubahan data asesi/skema kemudian tidak mengubah sertifikat.
            $table->string('nama_pemegang');
            $table->json('skema_judul'); // {id, en}
            $table->string('skema_nomor')->nullable();
            $table->json('unit_kompetensi'); // [{kode_unit, judul_unit}]
            $table->string('no_blanko', 50);
            $table->string('no_blanko_normal', 50)->unique();
            $table->string('no_sertifikat', 100);
            $table->string('no_sertifikat_normal', 100)->unique();
            $table->date('tanggal_aktif');
            $table->date('tanggal_expired');
            $table->string('status', 20)->default('berlaku'); // berlaku | tidak_berlaku (dicabut)
            $table->string('alasan_status', 1000)->nullable();
            $table->string('path_file');
            $table->string('nama_file_asli');
            $table->unsignedInteger('ukuran_file'); // KB
            $table->foreignId('diterbitkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('riwayat_penerbitan_sertifikasis', function (Blueprint $table) {
            $table->id();
            // Nama constraint eksplisit: nama bawaan melebihi batas 64 karakter MySQL.
            $table->foreignId('penerbitan_sertifikasi_id')
                ->constrained('penerbitan_sertifikasis', 'id', 'riwayat_penerbitan_fk')->cascadeOnDelete();
            $table->string('aksi', 40);
            $table->string('keterangan', 1000)->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_penerbitan_sertifikasis');
        Schema::dropIfExists('sertifikats');
        Schema::dropIfExists('dokumen_sertifikasis');
        Schema::dropIfExists('penerbitan_sertifikasis');
    }
};
