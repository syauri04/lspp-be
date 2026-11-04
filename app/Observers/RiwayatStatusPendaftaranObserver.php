<?php

namespace App\Observers;

use App\Models\RiwayatStatusPendaftaran;
use App\Notifications\StatusPendaftaranNotifikasi;
use Illuminate\Support\Facades\Log;

/**
 * Satu titik pemicu notifikasi status pendaftaran ke asesi: setiap perubahan status dicatat di
 * riwayat_status_pendaftarans, jadi semua jalur (website, CMS, webhook DOKU/Midtrans, MUK) ikut tercakup.
 * Status yang sudah punya notifikasi khusus di controller (awaiting_payment, rejected, dijadwalkan)
 * tidak dikirim ulang di sini.
 */
class RiwayatStatusPendaftaranObserver
{
    public const STATUS = ['submitted', 'paid', 'expired', 'asesmen_selesai'];

    public function created(RiwayatStatusPendaftaran $riwayat): void
    {
        if (! in_array($riwayat->status_ke, self::STATUS, true) || $riwayat->status_ke === $riwayat->status_dari) {
            return;
        }

        try {
            $pendaftaran = $riwayat->pendaftaranSertifikasi()->with(['asesi', 'skemaSertifikasi', 'penilaianMuk'])->first();
            $pendaftaran?->asesi?->notify(new StatusPendaftaranNotifikasi($pendaftaran, $riwayat->status_ke));
        } catch (\Throwable $e) {
            // Notifikasi tidak boleh menggagalkan perubahan status (mis. webhook pembayaran).
            Log::error('Gagal menyusun notifikasi status pendaftaran', ['riwayat_id' => $riwayat->id, 'error' => $e->getMessage()]);
        }
    }
}
