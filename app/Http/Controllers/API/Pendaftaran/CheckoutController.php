<?php

namespace App\Http\Controllers\API\Pendaftaran;

use App\Http\Controllers\Controller;
use App\Models\Asesi;
use App\Models\PendaftaranSertifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Midtrans\Config as MidtransConfig;
use Midtrans\Snap;

class CheckoutController extends Controller
{
    /**
     * GET /api/checkout/{kode_pendaftaran}
     * Dipanggil Next.js untuk render halaman "Ringkasan Pembayaran".
     * TIDAK generate snap token di sini -- biar halaman bisa dibuka meski Midtrans key belum siap,
     * dan tidak generate token yang tidak jadi dipakai kalau asesi belum tentu klik "Bayar Sekarang".
     */
    public function show(Request $request, PendaftaranSertifikasi $pendaftaran): JsonResponse
    {
        $asesi = $this->asesiAtauTolak($request, $pendaftaran);

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

        $pendaftaran->load('skemaSertifikasi:id,title,image');
        if ($pendaftaran->skemaSertifikasi) {
            $pendaftaran->skemaSertifikasi->image = asset($pendaftaran->skemaSertifikasi->image);
        }


        return response()->json([
            'data' => [
                'kode_pendaftaran' => $pendaftaran->kode_pendaftaran,
                'skema' => $pendaftaran->skemaSertifikasi,
                'jumlah' => $pembayaran->jumlah,
                'kode_transaksi' => $pembayaran->kode_transaksi,
                'batas_bayar' => $pembayaran->expired_at,
            ],
        ]);
    }

    /**
     * POST /api/checkout/{kode_pendaftaran}/snap-token
     * Dipanggil Next.js HANYA saat asesi klik tombol "Bayar Sekarang", bukan saat halaman dibuka.
     * Endpoint ini akan gagal selama MIDTRANS_SERVER_KEY belum diisi di .env -- itu wajar untuk sekarang.
     */
    public function snapToken(Request $request, PendaftaranSertifikasi $pendaftaran): JsonResponse
    {
        $asesi = $this->asesiAtauTolak($request, $pendaftaran);

        abort_unless($pendaftaran->status === 'awaiting_payment', 404);

        $pembayaran = $pendaftaran->pembayaran;
        abort_if(!$pembayaran || $pembayaran->status !== 'pending', 404);

        MidtransConfig::$serverKey = config('services.midtrans.server_key');
        MidtransConfig::$isProduction = config('services.midtrans.is_production');
        MidtransConfig::$isSanitized = true;
        MidtransConfig::$is3ds = true;

        $snapToken = Snap::getSnapToken([
            'transaction_details' => [
                'order_id' => $pembayaran->kode_transaksi,
                'gross_amount' => (int) $pembayaran->jumlah,
            ],
            'customer_details' => [
                'first_name' => $asesi->nama,
                'email' => $asesi->email,
            ],
            'item_details' => [[
                'id' => (string) $pendaftaran->skema_sertifikasi_id,
                'price' => (int) $pembayaran->jumlah,
                'quantity' => 1,
                'name' => $pendaftaran->skemaSertifikasi->nama_skema,
            ]],
        ]);

        return response()->json(['snap_token' => $snapToken]);
    }

    private function asesiAtauTolak(Request $request, PendaftaranSertifikasi $pendaftaran): Asesi
    {
        $user = $request->user();

        abort_unless($user instanceof Asesi, 403, 'Endpoint ini hanya untuk akun asesi.');
        abort_unless($pendaftaran->asesi_id === $user->id, 403, 'Anda tidak memiliki akses ke pendaftaran ini.');

        return $user;
    }
}
