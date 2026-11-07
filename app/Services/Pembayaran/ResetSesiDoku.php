<?php

namespace App\Services\Pembayaran;

use App\Models\BuktiPembayaran;
use App\Models\Pembayaran;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Reset sesi pembayaran DOKU oleh admin (CMS).
 *
 * Setelah asesi klik "Bayar Sekarang", link DOKU disimpan dengan nominal saat itu dan voucher terkunci.
 * Reset membuat tagihan kembali seperti belum dibuat sesi: voucher bisa dipasang/dilepas, lalu asesi
 * membuat sesi DOKU baru dengan nominal terbaru.
 *
 * - Kode transaksi (invoice_number DOKU) diganti baru, karena DOKU menolak invoice yang sama dengan
 *   nominal berbeda.
 * - Kode lama + URL + nominal disimpan di `sesi_doku_lama`. Link lama di DOKU masih bisa dibayar:
 *   webhook tetap mengenali invoice lama (lihat DokuWebhookController) dan menyatakan lunas bila
 *   nominalnya cukup, sedangkan notifikasi expired dari sesi lama diabaikan.
 */
class ResetSesiDoku
{
    public function reset(Pembayaran $pembayaran, User $admin): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran, $admin) {
            $tagihan = Pembayaran::query()->lockForUpdate()->findOrFail($pembayaran->id);
            $url = data_get($tagihan->gateway_payload, 'response.payment.url');

            $alasan = match (true) {
                $tagihan->status !== 'pending' => 'Tagihan tidak sedang menunggu pembayaran.',
                $tagihan->pendaftaranSertifikasi->status !== 'awaiting_payment' => 'Pendaftaran tidak sedang menunggu pembayaran.',
                blank($url) => 'Belum ada sesi pembayaran DOKU yang perlu direset.',
                $tagihan->buktiPembayaran()->where('status', BuktiPembayaran::MENUNGGU)->exists() => 'Bukti transfer manual sedang menunggu verifikasi.',
                default => null,
            };
            if ($alasan) {
                throw ValidationException::withMessages(['pembayaran' => [$alasan]]);
            }

            $riwayat = $tagihan->sesi_doku_lama ?? [];
            $riwayat[] = [
                'kode_transaksi' => $tagihan->kode_transaksi,
                'payment_url' => $url,
                'jumlah' => (string) $tagihan->jumlah,
                'direset_at' => now()->toIso8601String(),
                'direset_oleh' => $admin->id,
            ];

            $kodeLama = $tagihan->kode_transaksi;
            $tagihan->update([
                'kode_transaksi' => self::kodeBaru($tagihan),
                'gateway_payload' => null,
                'external_transaction_id' => null,
                'sesi_doku_lama' => $riwayat,
            ]);

            Log::info('Sesi DOKU direset admin', [
                'pembayaran_id' => $tagihan->id,
                'kode_lama' => $kodeLama,
                'kode_baru' => $tagihan->kode_transaksi,
                'admin_id' => $admin->id,
            ]);

            return $tagihan;
        });
    }

    /** Format sama dengan saat tagihan dibuat: LSPP-{8 huruf kode pendaftaran}-{timestamp}. */
    private static function kodeBaru(Pembayaran $tagihan): string
    {
        $dasar = 'LSPP-'.strtoupper(substr($tagihan->pendaftaranSertifikasi->kode_pendaftaran, 0, 8)).'-';
        $waktu = now()->timestamp;
        while (Pembayaran::where('kode_transaksi', $dasar.$waktu)->exists()) {
            $waktu++;
        }

        return $dasar.$waktu;
    }
}
