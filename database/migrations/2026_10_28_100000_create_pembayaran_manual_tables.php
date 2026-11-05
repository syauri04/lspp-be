<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opsi pembayaran manual (transfer bank + unggah bukti, diverifikasi admin) di samping DOKU.
 * Hanya MENAMBAH tabel baru; tabel lama (pembayarans, pendaftaran_sertifikasis) tidak diubah,
 * sehingga aman untuk data yang sudah ada.
 *
 * - pengaturans          : pengaturan aplikasi key-value (mis. saklar metode pembayaran DOKU / manual)
 * - rekening_pembayarans : rekening LSP tujuan transfer, dikelola admin di CMS
 * - bukti_pembayarans    : bukti transfer unggahan asesi + hasil verifikasi admin (riwayat per tagihan)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturans', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 100)->unique();
            $table->json('nilai')->nullable();
            $table->timestamps();
        });

        Schema::create('rekening_pembayarans', function (Blueprint $table) {
            $table->id();
            $table->string('nama_bank', 100);
            $table->string('nomor_rekening', 50);
            $table->string('atas_nama', 150);
            $table->string('keterangan', 255)->nullable(); // mis. cabang / kode bank
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['aktif', 'urutan']);
        });

        Schema::create('bukti_pembayarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembayaran_id')->constrained('pembayarans')->cascadeOnDelete();
            // Rekening bisa diubah/dihapus admin kemudian: simpan juga salinan (snapshot) rekening tujuan.
            $table->foreignId('rekening_pembayaran_id')->nullable()->constrained('rekening_pembayarans')->nullOnDelete();
            $table->json('rekening_tujuan');

            $table->string('nama_pengirim', 150);
            $table->string('bank_pengirim', 100);
            $table->date('tanggal_transfer');
            $table->decimal('jumlah_transfer', 12, 2);
            $table->string('catatan_asesi', 500)->nullable();

            // Berkas di disk privat; hanya path yang disimpan.
            $table->string('path_file');
            $table->string('nama_file_asli', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('ukuran_file'); // KB

            $table->enum('status', ['menunggu', 'disetujui', 'ditolak', 'dibatalkan'])->default('menunggu');
            $table->text('catatan_admin')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_at')->nullable();
            $table->timestamps();

            $table->index(['pembayaran_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bukti_pembayarans');
        Schema::dropIfExists('rekening_pembayarans');
        Schema::dropIfExists('pengaturans');
    }
};
