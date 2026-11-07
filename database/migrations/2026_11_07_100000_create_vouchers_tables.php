<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voucher potongan biaya sertifikasi.
 *
 * - vouchers: master voucher (CMS).
 * - voucher_pemakaians: riwayat pemakaian per tagihan (asesi + skema + potongan, snapshot).
 *   `aktif` = 1 untuk pemakaian yang sedang berlaku, NULL bila dilepas -> unique(pembayaran_id, aktif)
 *   menjamin satu tagihan hanya punya satu voucher aktif (MySQL mengizinkan banyak NULL).
 * - pembayarans: + jumlah_awal (harga sebelum potongan), potongan, voucher_id, voucher_kode.
 *
 * Hanya menambah tabel/kolom; data lama tetap. jumlah_awal diisi = jumlah untuk tagihan lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 32)->unique();
            $table->string('nama', 150)->nullable();
            $table->enum('tipe', ['nominal', 'persentase']);
            $table->decimal('nilai', 12, 2); // rupiah (nominal) atau persen 1-100 (persentase)
            $table->decimal('maks_potongan', 12, 2)->nullable(); // batas potongan untuk tipe persentase
            $table->unsignedInteger('kuota')->nullable(); // total pemakaian; null = tanpa batas
            $table->unsignedInteger('maks_per_asesi')->nullable()->default(1); // null = tanpa batas
            // dateTime (bukan timestamp): di MySQL/MariaDB dengan explicit_defaults_for_timestamp=OFF
            // (mis. hosting), timestamp NOT NULL kedua diberi default '0000-00-00' dan ditolak mode strict.
            // Nilai tetap disimpan dalam UTC oleh aplikasi.
            $table->dateTime('mulai_at');
            $table->dateTime('berakhir_at');
            $table->boolean('aktif')->default(true);
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['aktif', 'mulai_at', 'berakhir_at']);
        });

        Schema::create('voucher_pemakaians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->restrictOnDelete();
            $table->foreignId('pembayaran_id')->constrained('pembayarans')->cascadeOnDelete();
            $table->foreignId('asesi_id')->constrained('asesis')->restrictOnDelete();
            $table->foreignId('skema_sertifikasi_id')->constrained('skema_sertifikasi')->restrictOnDelete();
            $table->string('kode', 32); // snapshot kode saat dipakai
            $table->decimal('jumlah_awal', 12, 2);
            $table->decimal('potongan', 12, 2);
            $table->decimal('jumlah_akhir', 12, 2);
            $table->boolean('aktif')->nullable()->default(true);
            $table->timestamp('dilepas_at')->nullable();
            $table->timestamps();

            $table->unique(['pembayaran_id', 'aktif']);
            $table->index(['voucher_id', 'aktif']);
            $table->index(['voucher_id', 'asesi_id', 'aktif']);
        });

        Schema::table('pembayarans', function (Blueprint $table) {
            $table->decimal('jumlah_awal', 12, 2)->nullable()->after('jumlah');
            $table->decimal('potongan', 12, 2)->default(0)->after('jumlah_awal');
            $table->foreignId('voucher_id')->nullable()->after('potongan')->constrained('vouchers')->nullOnDelete();
            $table->string('voucher_kode', 32)->nullable()->after('voucher_id');
        });

        DB::table('pembayarans')->whereNull('jumlah_awal')->update(['jumlah_awal' => DB::raw('jumlah')]);
    }

    public function down(): void
    {
        Schema::table('pembayarans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voucher_id');
            $table->dropColumn(['jumlah_awal', 'potongan', 'voucher_kode']);
        });
        Schema::dropIfExists('voucher_pemakaians');
        Schema::dropIfExists('vouchers');
    }
};
