<?php

namespace App\Http\Controllers\API\Pendaftaran;

use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DokuWebhookController extends Controller
{
    /**
     * POST /api/doku/callback
     * Dipanggil server DOKU, TIDAK pakai middleware auth.
     *
     * Struktur payload dikonfirmasi dari dokumentasi resmi DOKU (HTTP Notification Sample):
     * {
     *   "service": {"id": "VIRTUAL_ACCOUNT"},
     *   "acquirer": {"id": "BCA"},
     *   "channel": {"id": "VIRTUAL_ACCOUNT_BCA"},
     *   "transaction": {"status": "SUCCESS", "date": "...", "original_request_id": "..."},
     *   "order": {"invoice_number": "INV-...", "amount": 150000}
     * }
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! $this->signatureValid($request)) {
            Log::warning('DOKU webhook: signature tidak valid', [
                'invoice_number' => $payload['order']['invoice_number'] ?? null,
            ]);
            abort(401, 'Invalid signature');
        }

        $invoiceNumber = $payload['order']['invoice_number'] ?? null;
        $pembayaran = Pembayaran::where('kode_transaksi', $invoiceNumber)->first();
        abort_unless($pembayaran, 404, 'Transaksi tidak ditemukan');

        // DOKU punya status di level ORDER (order.status: ORDER_GENERATED / ORDER_EXPIRED /
        // ORDER_RECOVERED), terpisah dari status per-percobaan channel (transaction.status).
        // Field ini dikonfirmasi ada di response Check Status API -- kalau payload notifikasi
        // yang masuk ke sini juga menyertakannya, kita proses duluan sebelum transaction.status,
        // karena ORDER_EXPIRED itu final untuk seluruh sesi checkout (beda dari transaction.status
        // FAILED yang cuma gagal di 1 channel dan masih bisa di-retry channel lain).
        $orderStatus = $payload['order']['status'] ?? null;

        if ($orderStatus === 'ORDER_EXPIRED') {
            if ($pembayaran->status !== 'expired') {
                DB::transaction(function () use ($pembayaran, $payload) {
                    $pendaftaran = $pembayaran->pendaftaranSertifikasi;
                    $statusSebelumnya = $pendaftaran->status;

                    $pembayaran->update(['status' => 'expired', 'gateway_payload' => $payload]);
                    $pendaftaran->update(['status' => 'expired']);

                    $pendaftaran->riwayatStatus()->create([
                        'status_dari' => $statusSebelumnya,
                        'status_ke' => 'expired',
                        'keterangan' => 'Order expired dikonfirmasi via webhook DOKU (order.status: ORDER_EXPIRED).',
                    ]);
                });
            }

            return response()->json(['message' => 'Order expired, status diperbarui']);
        }

        $transactionStatus = strtoupper($payload['transaction']['status'] ?? '');

        // PENTING (dikonfirmasi dari dokumentasi resmi DOKU -- HTTP Notification Best Practice):
        // "If you are integrating with Checkout, you must ignore the transaction.status FAILED."
        // Di halaman Checkout, asesi bisa ganti metode pembayaran kalau 1 metode gagal --
        // notifikasi FAILED itu cuma untuk 1 percobaan/channel, BUKAN berarti seluruh
        // sesi checkout gagal. Kalau kita set status 'failed' di sini, padahal asesi lagi
        // coba metode lain di halaman yang sama, itu salah dan bisa bikin sesi ke-block.
        if ($transactionStatus === 'FAILED') {
            Log::info('DOKU webhook: transaction FAILED diabaikan (asesi mungkin retry channel lain)', [
                'kode_transaksi' => $invoiceNumber,
                'channel' => $payload['channel']['id'] ?? null,
            ]);

            return response()->json(['message' => 'Diabaikan (FAILED pada Checkout tidak final)']);
        }

        $statusBaru = $this->mapStatus($transactionStatus);

        // Idempotency: skip kalau status tujuan sama persis dengan status sekarang
        // (notifikasi duplikat -- DOKU bisa kirim event yang sama lebih dari sekali,
        // sesuai peringatan resmi mereka soal ini).
        if ($pembayaran->status === $statusBaru) {
            return response()->json(['message' => 'Sudah diproses sebelumnya']);
        }

        DB::transaction(function () use ($pembayaran, $statusBaru, $payload) {
            $statusSebelumnya = $pembayaran->status;

            $pembayaran->update([
                'status' => $statusBaru,
                'metode_pembayaran' => $payload['channel']['id'] ?? $pembayaran->metode_pembayaran,
                'external_transaction_id' => $payload['transaction']['original_request_id'] ?? $pembayaran->external_transaction_id,
                'gateway_payload' => $payload,
                'paid_at' => $statusBaru === 'paid' ? now() : $pembayaran->paid_at,
            ]);

            if ($statusBaru === 'paid') {
                $pendaftaran = $pembayaran->pendaftaranSertifikasi;
                $pendaftaran->update(['status' => 'paid']);

                $pendaftaran->riwayatStatus()->create([
                    'status_dari' => $statusSebelumnya,
                    'status_ke' => 'paid',
                    'keterangan' => 'Pembayaran dikonfirmasi otomatis via webhook DOKU (channel: '
                        . ($payload['channel']['id'] ?? '-') . ').',
                ]);
            }
            // Status PENDING sengaja tidak mengubah status pendaftaran_sertifikasi --
            // tetap 'awaiting_payment', cukup update status internal di tabel pembayaran saja.
        });

        return response()->json(['message' => 'OK']);
    }

    private function signatureValid(Request $request): bool
    {
        $signatureHeader = $request->header('Signature');
        if (! $signatureHeader) {
            return false;
        }

        $digest = base64_encode(hash('sha256', $request->getContent(), true));

        // Request-Target untuk HTTP Notification = path Notification URL milik MERCHANT
        // (bukan path API DOKU), sesuai dokumentasi resmi "Signature Component from Request Header".
        $components = implode("\n", [
            'Client-Id:' . $request->header('Client-Id'),
            'Request-Id:' . $request->header('Request-Id'),
            'Request-Timestamp:' . $request->header('Request-Timestamp'),
            'Request-Target:/' . ltrim($request->path(), '/'),
            'Digest:' . $digest,
        ]);

        $expected = 'HMACSHA256=' . base64_encode(
            hash_hmac('sha256', $components, config('services.doku.secret_key'), true)
        );

        return hash_equals($expected, $signatureHeader);
    }

    private function mapStatus(string $transactionStatus): string
    {
        // Nilai dikonfirmasi dari dokumentasi resmi DOKU: SUCCESS, PENDING
        // (FAILED sudah ditangani terpisah di atas -- diabaikan, tidak masuk sini).
        // Status 'expired' ditangani lewat order.status di atas (ORDER_EXPIRED), BUKAN
        // lewat transaction.status -- keduanya field yang beda level (order vs per-channel).
        return match ($transactionStatus) {
            'SUCCESS' => 'paid',
            default => 'pending',
        };
    }
}
