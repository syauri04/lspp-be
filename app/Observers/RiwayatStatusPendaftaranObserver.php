<?php

namespace App\Observers;

use App\Models\PendaftaranSertifikasi;
use App\Models\PenilaianMuk;
use App\Models\RiwayatStatusPendaftaran;
use App\Notifications\StatusPendaftaranNotifikasi;
use App\Support\Cms\NotifikasiAdmin;
use Illuminate\Support\Facades\Log;

/**
 * Satu titik pemicu notifikasi status pendaftaran ke asesi: setiap perubahan status dicatat di
 * riwayat_status_pendaftarans, jadi semua jalur (website, CMS, webhook DOKU/Midtrans, MUK) ikut tercakup.
 * Status yang sudah punya notifikasi khusus di controller (awaiting_payment, rejected, dijadwalkan)
 * tidak dikirim ulang di sini.
 */
class RiwayatStatusPendaftaranObserver
{
    public const STATUS = ['submitted', 'paid', 'expired', 'asesmen_selesai', 'completed'];

    public function created(RiwayatStatusPendaftaran $riwayat): void
    {
        if (! in_array($riwayat->status_ke, self::STATUS, true) || $riwayat->status_ke === $riwayat->status_dari) {
            return;
        }

        try {
            $pendaftaran = $riwayat->pendaftaranSertifikasi()->with(['asesi', 'skemaSertifikasi', 'penilaianMuk', 'jadwalAsesmen'])->first();
            $pendaftaran?->asesi?->notify(new StatusPendaftaranNotifikasi($pendaftaran, $riwayat->status_ke));
            if ($pendaftaran) {
                $this->kabariAdmin($pendaftaran, $riwayat->status_ke);
            }
        } catch (\Throwable $e) {
            // Notifikasi tidak boleh menggagalkan perubahan status (mis. webhook pembayaran).
            Log::error('Gagal menyusun notifikasi status pendaftaran', ['riwayat_id' => $riwayat->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Lonceng CMS untuk kejadian yang perlu tindak lanjut admin. "submitted" sudah dikirim
     * PendaftaranMasukNotifikasi (email + database) dari controller, jadi tidak diulang di sini.
     */
    private function kabariAdmin(PendaftaranSertifikasi $pendaftaran, string $status): void
    {
        $nama = $pendaftaran->asesi->name ?? 'Asesi';
        $skema = $pendaftaran->skemaSertifikasi->title['id'] ?? '-';

        match ($status) {
            'paid' => NotifikasiAdmin::pemberitahuan(
                'penjadwalan',
                'Pembayaran lunas, perlu dijadwalkan',
                "{$nama} telah melunasi pembayaran skema {$skema}"
                    .($pendaftaran->pembayaran?->metode_pembayaran ? ' ('.$pendaftaran->pembayaran->metode_pembayaran.')' : '').'.',
                route('penjadwalan-asesmen.create', $pendaftaran->kode_pendaftaran),
            ),
            'asesmen_selesai' => NotifikasiAdmin::pemberitahuan(
                'asesmen',
                'Asesmen selesai, sertifikat perlu diproses',
                "Asesmen {$nama} untuk skema {$skema} telah selesai. Unggah SK".($pendaftaran->penilaianMuk?->status === PenilaianMuk::STATUS_SELESAI_KOMPETEN ? ' dan e-sertifikat.' : '.'),
                ($penerbitan = $pendaftaran->penerbitanSertifikasi()->first())
                    ? route('penerbitan-sertifikat.show', $penerbitan)
                    : ($pendaftaran->jadwalAsesmen ? route('asesmen.show', $pendaftaran->jadwalAsesmen) : route('asesmen.index')),
            ),
            default => null,
        };
    }
}
