<?php

namespace App\Http\Controllers\API\Pendaftaran;

use App\Http\Controllers\Controller;
use App\Models\Asesi;
use App\Models\PendaftaranSertifikasi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    /**
     * GET /api/checkout/{kode_pendaftaran}
     * Dipanggil Next.js untuk render halaman "Ringkasan Pembayaran".
     * TIDAK memanggil DOKU di sini -- biar halaman bisa dibuka meski kredensial DOKU belum siap.
     */
    public function show(Request $request, PendaftaranSertifikasi $pendaftaran): JsonResponse
    {
        $this->asesiAtauTolak($request, $pendaftaran);

        abort_unless(
            $pendaftaran->status === 'awaiting_payment',
            404,
            'Pendaftaran ini tidak dalam status menunggu pembayaran.'
        );

        $pembayaran = $pendaftaran->pembayaran;

        abort_if(
            !$pembayaran || $pembayaran->status !== 'pending',
            404,
            'Tagihan pembayaran tidak ditemukan atau sudah tidak berlaku.'
        );

        if ($pembayaran->expired_at && now()->greaterThanOrEqualTo($pembayaran->expired_at)) {
            $pembayaran->update(['status' => 'expired']);
            $pendaftaran->update(['status' => 'expired']);

            abort(410, 'Batas waktu pembayaran untuk pendaftaran ini sudah lewat.');
        }

        $pendaftaran->load('skemaSertifikasi:id,title,image');

        return response()->json([
            'data' => [
                'kode_pendaftaran' => $pendaftaran->kode_pendaftaran,
                'skema' => $pendaftaran->skemaSertifikasi,
                'jumlah' => $pembayaran->jumlah,
                'kode_transaksi' => $pembayaran->kode_transaksi,
                'batas_bayar' => $pembayaran->expired_at,
                // Kalau asesi buka ulang halaman checkout sebelum bayar, payment_url lama
                // (kalau sudah pernah di-generate) bisa langsung dipakai lagi tanpa panggil
                // DOKU sekali lagi -- lihat gateway_payload yang disimpan waktu pay() dipanggil.
                'payment_url' => data_get($pembayaran->gateway_payload, 'response.payment.url'),
            ],
        ]);
    }

    /**
     * POST /api/checkout/{kode_pendaftaran}/pay
     * Dipanggil Next.js saat asesi klik tombol "Bayar Sekarang".
     * DOKU Checkout balikin payment_url (halaman hosted DOKU) -- FE tinggal redirect
     * browser asesi ke URL itu, BUKAN munculin widget/popup seperti Midtrans Snap.
     */
    public function pay(Request $request, PendaftaranSertifikasi $pendaftaran): JsonResponse
    {
        $asesi = $this->asesiAtauTolak($request, $pendaftaran);

        abort_unless($pendaftaran->status === 'awaiting_payment', 404);

        $pembayaran = $pendaftaran->pembayaran;
        abort_if(!$pembayaran || $pembayaran->status !== 'pending', 404);

        if ($pembayaran->expired_at && now()->greaterThanOrEqualTo($pembayaran->expired_at)) {
            $pembayaran->update(['status' => 'expired']);
            $pendaftaran->update(['status' => 'expired']);

            abort(410, 'Batas waktu pembayaran untuk pendaftaran ini sudah lewat.');
        }

        // Kalau payment_url sudah pernah di-generate dan belum expired di sisi DOKU,
        // reuse saja -- hindari bikin sesi checkout baru tiap kali asesi klik ulang.
        $existingUrl = data_get($pembayaran->gateway_payload, 'response.payment.url');
        if ($existingUrl) {
            return response()->json(['payment_url' => $existingUrl]);
        }

        $baseUrl = config('services.doku.is_production')
            ? 'https://api.doku.com'
            : 'https://api-sandbox.doku.com';

        $path = '/checkout/v1/payment';
        $requestId = (string) Str::uuid();
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');

        // Sesuai dokumentasi resmi DOKU (Backend Integration -- Basic Request):
        // https://developers.doku.com/accept-payments/doku-checkout/integration-guide/backend-integration
        $body = [
            'order' => [
                'amount' => (int) $pembayaran->jumlah,
                'invoice_number' => $pembayaran->kode_transaksi,
                'callback_url' => rtrim(config('app.frontend_url'), '/') . '/checkout/' . $pendaftaran->kode_pendaftaran . '/selesai',
                'auto_redirect' => true,
            ],
            'payment' => [
                'payment_due_date' => $pembayaran->expired_at
                    ? max(1, now()->diffInMinutes($pembayaran->expired_at))
                    : 1440,
            ],
            'customer' => [
                'name' => $asesi->nama,
                'email' => $asesi->email,
            ],
        ];

        $bodyJson = json_encode($body);
        $digest = base64_encode(hash('sha256', $bodyJson, true));

        // Urutan komponen & format ini WAJIB persis seperti ini -- dikonfirmasi dari
        // dokumentasi resmi "Signature Component from Request Header" DOKU.
        $signatureComponents = implode("\n", [
            'Client-Id:' . config('services.doku.client_id'),
            'Request-Id:' . $requestId,
            'Request-Timestamp:' . $timestamp,
            'Request-Target:' . $path,
            'Digest:' . $digest,
        ]);

        $signature = 'HMACSHA256=' . base64_encode(
            hash_hmac('sha256', $signatureComponents, config('services.doku.secret_key'), true)
        );

        try {
            $response = Http::withHeaders([
                'Client-Id' => config('services.doku.client_id'),
                'Request-Id' => $requestId,
                'Request-Timestamp' => $timestamp,
                'Signature' => $signature,
                'Content-Type' => 'application/json',
            ])->withBody($bodyJson, 'application/json')
                ->post($baseUrl . $path)
                ->throw();
        } catch (RequestException $e) {
            Log::error('DOKU checkout gagal dibuat', [
                'kode_transaksi' => $pembayaran->kode_transaksi,
                'response' => $e->response?->body(),
            ]);

            abort(502, 'Gagal membuat sesi pembayaran, coba lagi beberapa saat.');
        }

        $result = $response->json();

        $pembayaran->update([
            'external_transaction_id' => data_get($result, 'response.payment.token_id'),
            'gateway_payload' => $result,
        ]);

        return response()->json([
            'payment_url' => data_get($result, 'response.payment.url'),
        ]);
    }

    private function asesiAtauTolak(Request $request, PendaftaranSertifikasi $pendaftaran): Asesi
    {
        $user = $request->user();

        abort_unless($user instanceof Asesi, 403, 'Endpoint ini hanya untuk akun asesi.');
        abort_unless($pendaftaran->asesi_id === $user->id, 403, 'Anda tidak memiliki akses ke pendaftaran ini.');

        return $user;
    }
}
