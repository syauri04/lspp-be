<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk badge sidebar & lonceng CMS (dihitung di setiap halaman CMS):
 * - pendaftaran_sertifikasis.status (pendaftaran submitted, lunas belum dijadwalkan)
 * - dokumen_penilaians.status (APL.01 menunggu review)
 * - notifications (notifiable, read_at) (jumlah belum dibaca per admin)
 * Hanya menambah index; data tidak berubah.
 */
return new class extends Migration
{
    private const INDEX = [
        'pendaftaran_sertifikasis' => ['pendaftaran_status_index', ['status']],
        'dokumen_penilaians' => ['dokumen_penilaian_status_index', ['status']],
        'notifications' => ['notifications_notifiable_read_index', ['notifiable_type', 'notifiable_id', 'read_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEX as $tabel => [$nama, $kolom]) {
            if (! Schema::hasIndex($tabel, $nama)) {
                Schema::table($tabel, fn (Blueprint $t) => $t->index($kolom, $nama));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEX as $tabel => [$nama]) {
            if (Schema::hasIndex($tabel, $nama)) {
                Schema::table($tabel, fn (Blueprint $t) => $t->dropIndex($nama));
            }
        }
    }
};
