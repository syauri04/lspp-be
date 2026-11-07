<?php

namespace App\Support\Cms;

use App\Models\BuktiPembayaran;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\PendaftaranSertifikasi;

/**
 * Jumlah pekerjaan yang menunggu admin, untuk badge menu sidebar CMS.
 * Kriteria sama persis dengan filter bawaan halaman masing-masing, sehingga angka badge = isi halaman.
 * Dihitung sekali per request (once) — 4 query COUNT berindeks.
 */
class HitungMenu
{
    /** @return array{pendaftaran:int, verifikasi_pembayaran:int, penjadwalan:int, review_apl01:int} */
    public static function semua(): array
    {
        return once(fn () => [
            // Pendaftaran baru yang perlu direview & di-approve.
            'pendaftaran' => PendaftaranSertifikasi::where('status', 'submitted')->count(),
            // Bukti transfer manual menunggu verifikasi.
            'verifikasi_pembayaran' => BuktiPembayaran::where('status', BuktiPembayaran::MENUNGGU)->count(),
            // Sudah lunas tetapi belum dijadwalkan (sama dengan PenjadwalanAsesmenController@index).
            'penjadwalan' => PendaftaranSertifikasi::where('status', 'paid')->whereDoesntHave('jadwalAsesmen')->count(),
            // FR.APL.01 menunggu review admin (filter bawaan ReviewApl01Controller@index).
            'review_apl01' => DokumenPenilaian::query()
                ->kode(JenisDokumenMuk::APL_01)
                ->where('status', DokumenPenilaian::STATUS_MENUNGGU_REVIEW)
                ->count(),
        ]);
    }
}
