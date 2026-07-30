<?php

namespace App\Http\Controllers\API\Pendaftaran;

use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    /**
     * POST /api/midtrans/callback
     * Dipanggil langsung oleh server Midtrans, TIDAK pakai middleware auth.
     * Keamanan bergantung sepenuhnya pada verifikasi signature_key di bawah.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (!$this->signatureValid($payload)) {
            Log::warning('Midtrans webhook: signature tidak valid', ['order_id' => $payload['order_id'] ?? null]);
            abort(403, 'Invalid signature');
        }

        $pembayaran = Pembayaran::where('kode_transaksi', $payload['order_id'] ?? null)->first();
        abort_unless($pembayaran, 404, 'Transaksi tidak ditemukan');

        $statusBaru = $this->mapStatus($payload['transaction_status'] ?? '', $payload['fraud_status'] ?? null);

        // Idempotency: skip HANYA kalau status yang mau di-set sama persis dengan status sekarang
        // (berarti notifikasi duplikat dari Midtrans, aman diabaikan). Sengaja TIDAK skip berdasarkan
        // "status sudah final", karena refund/partial_refund justru datang SETELAH status 'paid'.
        if ($pembayaran->status === $statusBaru) {
            return response()->json(['message' => 'Sudah diproses sebelumnya']);
        }

        DB::transaction(function () use ($pembayaran, $statusBaru, $payload) {
            $statusSebelumnya = $pembayaran->status;

            $pembayaran->update([
                'status' => $statusBaru,
                'metode_pembayaran' => $payload['payment_type'] ?? $pembayaran->metode_pembayaran,
                'midtrans_transaction_id' => $payload['transaction_id'] ?? null,
                'midtrans_payload' => $payload,
                'paid_at' => $statusBaru === 'paid' ? now() : $pembayaran->paid_at,
                'expired_at' => $statusBaru === 'expired' ? now() : $pembayaran->expired_at,
            ]);

            $pendaftaran = $pembayaran->pendaftaranSertifikasi;

            // Status pendaftaran_sertifikasi HANYA diikutkan berubah untuk 'paid' dan 'expired'.
            // Untuk 'refunded', status pendaftaran sengaja TIDAK otomatis diubah -- itu keputusan
            // bisnis admin (misal: sertifikasi tetap lanjut meski di-refund sebagian, atau dibatalkan
            // manual). Silakan sesuaikan kalau kamu mau otomatisasi lebih jauh.
            if ($statusBaru === 'paid') {
                $pendaftaran->update(['status' => 'paid']);
            } elseif ($statusBaru === 'expired') {
                $pendaftaran->update(['status' => 'expired']);
            }

            $pendaftaran->riwayatStatus()->create([
                'status_dari' => $statusSebelumnya,
                'status_ke' => $statusBaru,
                'keterangan' => 'Update otomatis via webhook Midtrans (transaction_status: ' . ($payload['transaction_status'] ?? '-') . ').',
            ]);
        });

        return response()->json(['message' => 'OK']);
    }

    private function signatureValid(array $payload): bool
    {
        if (!isset($payload['order_id'], $payload['status_code'], $payload['gross_amount'], $payload['signature_key'])) {
            return false;
        }

        $serverKey = config('services.midtrans.server_key');

        $expected = hash(
            'sha512',
            $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $serverKey
        );

        return hash_equals($expected, $payload['signature_key']);
    }

    private function mapStatus(string $transactionStatus, ?string $fraudStatus): string
    {
        return match (true) {
            // Kartu kredit tanpa fraud detection tambahan -> langsung paid
            $transactionStatus === 'capture' && $fraudStatus === 'accept' => 'paid',
            // Kartu kredit yang di-flag mencurigakan oleh Midtrans FDS -> jangan langsung paid,
            // biarkan tetap 'pending' sampai ditinjau manual di dashboard Midtrans.
            $transactionStatus === 'capture' && $fraudStatus === 'challenge' => 'pending',
            $transactionStatus === 'capture' && $fraudStatus === 'deny' => 'failed',

            // VA, e-wallet (Gopay/OVO/Dana), QRIS, dll -> settlement setelah dibayar
            $transactionStatus === 'settlement' => 'paid',

            in_array($transactionStatus, ['cancel', 'deny'], true) => 'failed',
            $transactionStatus === 'expire' => 'expired',

            // Refund penuh maupun sebagian, keduanya kita catat sebagai 'refunded'.
            // Kalau butuh bedakan jumlah refund parsial, simpan detailnya dari midtrans_payload.
            in_array($transactionStatus, ['refund', 'partial_refund'], true) => 'refunded',

            default => 'pending',
        };
    }
}
