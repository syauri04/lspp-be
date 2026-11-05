<?php

namespace App\Services\Pembayaran;

use App\Models\BuktiPembayaran;
use App\Models\Pembayaran;
use Illuminate\Support\Facades\DB;

/**
 * Konfirmasi pembayaran LUNAS: pembayaran -> paid, pendaftaran -> paid, catat riwayat status
 * (riwayat memicu notifikasi "Pembayaran berhasil" ke asesi lewat RiwayatStatusPendaftaranObserver).
 * Dipakai webhook DOKU, verifikasi transfer manual oleh admin (PembayaranManual) dan perintah simulasi
 * lokal `pembayaran:simulasi-lunas`.
 */
class KonfirmasiPembayaran
{
    /** @return bool false bila sudah lunas sebelumnya (idempoten, tidak ada perubahan). */
    public function lunas(Pembayaran $pembayaran, string $keterangan, ?string $metode = null, ?string $idTransaksi = null, ?array $payload = null, ?int $diubahOleh = null): bool
    {
        return DB::transaction(function () use ($pembayaran, $keterangan, $metode, $idTransaksi, $payload, $diubahOleh) {
            $p = Pembayaran::query()->lockForUpdate()->findOrFail($pembayaran->id);
            if ($p->status === 'paid') {
                return false;
            }

            $statusSebelumnya = $p->status;
            $p->update([
                'status' => 'paid',
                'metode_pembayaran' => $metode ?? $p->metode_pembayaran,
                'external_transaction_id' => $idTransaksi ?? $p->external_transaction_id,
                'gateway_payload' => $payload ?? $p->gateway_payload,
                'paid_at' => now(),
            ]);

            // Lunas lewat jalur lain (mis. DOKU) saat bukti transfer manual masih menunggu: tutup buktinya
            // agar admin tidak memverifikasi pembayaran yang sama dua kali.
            $p->buktiPembayaran()->where('status', BuktiPembayaran::MENUNGGU)->update([
                'status' => BuktiPembayaran::DIBATALKAN,
                'catatan_admin' => 'Tagihan sudah lunas melalui '.($metode ?? 'jalur lain').'.',
                'updated_at' => now(),
            ]);

            $pendaftaran = $p->pendaftaranSertifikasi;
            $pendaftaran->update(['status' => 'paid']);
            $pendaftaran->riwayatStatus()->create([
                'status_dari' => $statusSebelumnya,
                'status_ke' => 'paid',
                'keterangan' => $keterangan,
                'changed_by' => $diubahOleh, // admin CMS (verifikasi manual); null untuk webhook/sistem
            ]);

            return true;
        });
    }
}
