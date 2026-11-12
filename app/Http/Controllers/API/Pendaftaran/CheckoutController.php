<?php

namespace App\Http\Controllers\API\Pendaftaran;

use App\Http\Controllers\Controller;
use App\Models\Asesi;
use App\Models\BuktiPembayaran;
use App\Models\Pembayaran;
use App\Models\PendaftaranSertifikasi;
use App\Models\Pengaturan;
use App\Models\RekeningPembayaran;
use App\Services\Pembayaran\PembayaranManual;
use App\Services\Pembayaran\VoucherCheckout;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            ! $pembayaran || $pembayaran->status !== 'pending',
            404,
            'Tagihan pembayaran tidak ditemukan atau sudah tidak berlaku.'
        );

        // Bukti transfer yang sedang diverifikasi admin tidak boleh hangus karena batas waktu.
        if ($pembayaran->expired_at && now()->greaterThanOrEqualTo($pembayaran->expired_at) && ! $pembayaran->buktiMenunggu) {
            $this->kedaluwarsakan($pendaftaran, $pembayaran);

            abort(410, 'Batas waktu pembayaran untuk pendaftaran ini sudah lewat.');
        }

        $pendaftaran->load('skemaSertifikasi:id,title,image');
        // Transfer manual hanya ditawarkan bila diaktifkan DAN ada rekening aktif.
        $rekening = Pengaturan::aktif(Pengaturan::PEMBAYARAN_MANUAL_AKTIF)
            ? RekeningPembayaran::query()->aktif()->get(['id', 'nama_bank', 'nomor_rekening', 'atas_nama', 'keterangan'])
            : collect();
        $manualAktif = $rekening->isNotEmpty();
        $buktiTerakhir = $pembayaran->buktiPembayaran()->with('rekening')->first();

        return response()->json([
            'data' => [
                'kode_pendaftaran' => $pendaftaran->kode_pendaftaran,
                'skema' => $pendaftaran->skemaSertifikasi,
                'jumlah' => $pembayaran->jumlah,
                ...$this->ringkasanVoucher($pembayaran),
                'kode_transaksi' => $pembayaran->kode_transaksi,
                'batas_bayar' => $pembayaran->expired_at,
                // Kalau asesi buka ulang halaman checkout sebelum bayar, payment_url lama
                // (kalau sudah pernah di-generate) bisa langsung dipakai lagi tanpa panggil
                // DOKU sekali lagi -- lihat gateway_payload yang disimpan waktu pay() dipanggil.
                'payment_url' => data_get($pembayaran->gateway_payload, 'response.payment.url'),
                // Metode yang tersedia (saklar di CMS: Pengaturan Pembayaran).
                'metode' => [
                    'doku' => Pengaturan::aktif(Pengaturan::PEMBAYARAN_DOKU_AKTIF),
                    'manual' => $manualAktif,
                ],
                'rekening' => $rekening,
                'bukti_terakhir' => $buktiTerakhir ? $this->buktiRingkas($buktiTerakhir) : null,
                'unggah' => ['mimes' => BuktiPembayaran::MIMES, 'maks_kb' => BuktiPembayaran::MAKS_KB],
            ],
        ]);
    }

    /**
     * POST /api/checkout/{kode_pendaftaran}/bukti-transfer  (multipart)
     * Asesi mengunggah bukti transfer manual; admin memverifikasi di CMS (Verifikasi Pembayaran).
     */
    public function kirimBuktiTransfer(Request $request, PendaftaranSertifikasi $pendaftaran, PembayaranManual $manual): JsonResponse
    {
        $this->asesiAtauTolak($request, $pendaftaran);

        $data = $request->validate([
            'rekening_pembayaran_id' => ['required', 'integer', Rule::exists('rekening_pembayarans', 'id')->where('aktif', true)],
            'nama_pengirim' => ['required', 'string', 'max:150'],
            'bank_pengirim' => ['required', 'string', 'max:100'],
            // Tidak boleh di masa depan (tanggal WIB); batas bawah = tanggal tagihan dibuat (pendaftaran disetujui).
            'tanggal_transfer' => [
                'required', 'date_format:Y-m-d',
                'before_or_equal:'.now(config('muk.zona_waktu'))->toDateString(),
                'after_or_equal:'.($pendaftaran->pembayaran?->created_at?->timezone(config('muk.zona_waktu'))->toDateString() ?? '2000-01-01'),
            ],
            'jumlah_transfer' => ['required', 'numeric', 'min:1', 'max:9999999999'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'mimes:'.implode(',', BuktiPembayaran::MIMES), 'max:'.BuktiPembayaran::MAKS_KB],
        ], [
            'rekening_pembayaran_id.exists' => 'Pilih rekening tujuan yang tersedia.',
            'tanggal_transfer.before_or_equal' => 'Tanggal transfer tidak boleh di masa depan.',
            'tanggal_transfer.after_or_equal' => 'Tanggal transfer tidak boleh sebelum pendaftaran disetujui.',
            'file.mimes' => 'Bukti transfer harus berformat JPG, PNG, atau PDF.',
            'file.max' => 'Ukuran bukti transfer maksimal 5 MB.',
        ]);

        $bukti = $manual->kirimBukti($pendaftaran, $data, $request->file('file'));

        return response()->json([
            'message' => 'Bukti transfer berhasil dikirim dan akan diverifikasi admin LSP.',
            'data' => $this->buktiRingkas($bukti->load('rekening')),
        ], 201);
    }

    /**
     * POST /api/checkout/{kode_pendaftaran}/voucher  {kode}
     * Pasang (atau ganti) voucher. Total dihitung ulang di server; respons = ringkasan harga terbaru.
     */
    public function pasangVoucher(Request $request, PendaftaranSertifikasi $pendaftaran, VoucherCheckout $voucher): JsonResponse
    {
        $asesi = $this->asesiAtauTolak($request, $pendaftaran);
        $data = $request->validate(
            ['kode' => ['required', 'string', 'max:32']],
            ['kode.required' => 'Masukkan kode voucher.', 'kode.max' => 'Kode voucher tidak valid.'],
        );

        $pembayaran = $voucher->pasang($this->tagihanPending($pendaftaran), $asesi, $data['kode']);

        return response()->json([
            'message' => $pembayaran->status === 'paid'
                ? 'Voucher berhasil digunakan. Tagihan Anda lunas.'
                : 'Voucher berhasil digunakan.',
            'data' => $this->hasilVoucher($pembayaran),
        ]);
    }

    /** DELETE /api/checkout/{kode_pendaftaran}/voucher */
    public function lepasVoucher(Request $request, PendaftaranSertifikasi $pendaftaran, VoucherCheckout $voucher): JsonResponse
    {
        $this->asesiAtauTolak($request, $pendaftaran);
        $pembayaran = $voucher->lepas($this->tagihanPending($pendaftaran));

        return response()->json([
            'message' => 'Voucher dilepas.',
            'data' => $this->hasilVoucher($pembayaran),
        ]);
    }

    private function tagihanPending(PendaftaranSertifikasi $pendaftaran): Pembayaran
    {
        abort_unless($pendaftaran->status === 'awaiting_payment', 404, 'Pendaftaran ini tidak dalam status menunggu pembayaran.');
        $pembayaran = $pendaftaran->pembayaran;
        abort_if(! $pembayaran || $pembayaran->status !== 'pending', 404, 'Tagihan pembayaran tidak ditemukan atau sudah tidak berlaku.');

        return $pembayaran;
    }

    private function hasilVoucher(Pembayaran $pembayaran): array
    {
        return ['status' => $pembayaran->status, 'jumlah' => $pembayaran->jumlah, ...$this->ringkasanVoucher($pembayaran)];
    }

    /** Rincian harga & voucher untuk ringkasan pembayaran di checkout. */
    private function ringkasanVoucher(Pembayaran $pembayaran): array
    {
        $pemakaian = $pembayaran->voucherPemakaian()->with('voucher')->first();

        return [
            'harga_awal' => number_format($pembayaran->hargaAwal(), 2, '.', ''),
            'potongan' => $pembayaran->potongan ?? '0.00',
            'voucher' => $pemakaian ? [
                'kode' => $pemakaian->kode,
                'nama' => $pemakaian->voucher?->nama,
                'keterangan' => $pemakaian->voucher?->labelNilai(),
            ] : null,
            // null = voucher boleh dipasang/dilepas; selain itu alasan terkunci (ditampilkan di FE).
            'voucher_terkunci' => VoucherCheckout::alasanTerkunci($pembayaran),
        ];
    }

    /** Ringkasan bukti transfer untuk asesi (tanpa path berkas). */
    private function buktiRingkas(BuktiPembayaran $bukti): array
    {
        return [
            'id' => $bukti->id,
            'status' => $bukti->status,
            'status_label' => BuktiPembayaran::STATUS_LABEL[$bukti->status] ?? $bukti->status,
            'catatan_admin' => $bukti->catatan_admin,
            'rekening_tujuan' => $bukti->rekening_tujuan,
            'nama_pengirim' => $bukti->nama_pengirim,
            'bank_pengirim' => $bukti->bank_pengirim,
            'tanggal_transfer' => $bukti->tanggal_transfer?->toDateString(),
            'jumlah_transfer' => $bukti->jumlah_transfer,
            'dikirim_at' => $bukti->created_at?->toIso8601String(),
        ];
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
        abort_if(! $pembayaran || $pembayaran->status !== 'pending', 404);

        abort_unless(
            Pengaturan::aktif(Pengaturan::PEMBAYARAN_DOKU_AKTIF),
            409,
            'Pembayaran online sedang tidak tersedia. Silakan gunakan transfer manual.'
        );
        // Cegah pembayaran ganda: bukti transfer manual masih diverifikasi admin.
        abort_if(
            $pembayaran->buktiMenunggu()->exists(),
            409,
            'Bukti transfer Anda sedang diverifikasi admin. Tunggu hasil verifikasi sebelum membayar dengan metode lain.'
        );

        if ($pembayaran->expired_at && now()->greaterThanOrEqualTo($pembayaran->expired_at)) {
            $this->kedaluwarsakan($pendaftaran, $pembayaran);

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
                'callback_url' => rtrim(config('app.frontend_url'), '/').'/checkout/'.$pendaftaran->kode_pendaftaran.'/selesai',
                'auto_redirect' => true,
                // Rincian di invoice/halaman DOKU: skema (harga asli) + potongan voucher.
                'line_items' => self::lineItems($pendaftaran, $pembayaran),
            ],
            'payment' => [
                'payment_due_date' => $pembayaran->expired_at
                    ? max(1, now()->diffInMinutes($pembayaran->expired_at))
                    : 1440,
            ],
            'customer' => [
                'name' => $asesi->name,
                'email' => $asesi->email,
            ],
        ];

        $bodyJson = json_encode($body);
        $digest = base64_encode(hash('sha256', $bodyJson, true));

        // Urutan komponen & format ini WAJIB persis seperti ini -- dikonfirmasi dari
        // dokumentasi resmi "Signature Component from Request Header" DOKU.
        $signatureComponents = implode("\n", [
            'Client-Id:'.config('services.doku.client_id'),
            'Request-Id:'.$requestId,
            'Request-Timestamp:'.$timestamp,
            'Request-Target:'.$path,
            'Digest:'.$digest,
        ]);

        $signature = 'HMACSHA256='.base64_encode(
            hash_hmac('sha256', $signatureComponents, config('services.doku.secret_key'), true)
        );

        try {
            $response = Http::connectTimeout(10)->timeout(20)->withHeaders([
                'Client-Id' => config('services.doku.client_id'),
                'Request-Id' => $requestId,
                'Request-Timestamp' => $timestamp,
                'Signature' => $signature,
                'Content-Type' => 'application/json',
            ])->withBody($bodyJson, 'application/json')
                ->post($baseUrl.$path)
                ->throw();
        } catch (RequestException $e) {
            Log::error('DOKU checkout gagal dibuat', [
                'kode_transaksi' => $pembayaran->kode_transaksi,
                'response' => $e->response?->body(),
            ]);

            abort(502, 'Gagal membuat sesi pembayaran, coba lagi beberapa saat.');
        } catch (ConnectionException $e) {
            // Server DOKU tidak dapat dihubungi (timeout / jaringan) -- bukan kesalahan data asesi.
            Log::error('DOKU tidak dapat dihubungi', [
                'kode_transaksi' => $pembayaran->kode_transaksi,
                'error' => $e->getMessage(),
            ]);

            abort(503, 'Layanan pembayaran sedang tidak dapat dihubungi. Silakan coba beberapa saat lagi.');
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

    /**
     * Rincian tagihan untuk DOKU: 1 x skema dengan harga asli, lalu baris potongan voucher (harga minus).
     * DOKU menolak sesi bila jumlah price x quantity != order.amount ("AMOUNT NOT MATCH"), jadi bila data
     * lama tidak konsisten (harga awal - potongan != tagihan) cukup satu baris seharga tagihan.
     *
     * @return array<int, array{name: string, price: int, quantity: int}>
     */
    public static function lineItems(PendaftaranSertifikasi $pendaftaran, Pembayaran $pembayaran): array
    {
        $nama = mb_substr('Sertifikasi '.($pendaftaran->skemaSertifikasi?->title['id'] ?? 'Kompetensi'), 0, 255);
        $tagihan = (int) round((float) $pembayaran->jumlah);
        $hargaAwal = $pembayaran->hargaAwal();
        $potongan = (int) round((float) ($pembayaran->potongan ?? 0));

        if ($potongan <= 0 || $hargaAwal - $potongan !== $tagihan) {
            return [['name' => $nama, 'price' => $tagihan, 'quantity' => 1]];
        }

        $voucher = $pembayaran->voucher_kode ? " {$pembayaran->voucher_kode}" : '';

        return [
            ['name' => $nama, 'price' => $hargaAwal, 'quantity' => 1],
            ['name' => mb_substr("Potongan voucher{$voucher}", 0, 255), 'price' => -$potongan, 'quantity' => 1],
        ];
    }

    private function asesiAtauTolak(Request $request, PendaftaranSertifikasi $pendaftaran): Asesi
    {
        $user = $request->user();

        abort_unless($user instanceof Asesi, 403, 'Endpoint ini hanya untuk akun asesi.');
        abort_unless($pendaftaran->asesi_id === $user->id, 403, 'Anda tidak memiliki akses ke pendaftaran ini.');

        return $user;
    }

    /** Batas bayar lewat: tandai kedaluwarsa + catat riwayat (memicu notifikasi ke asesi). */
    private function kedaluwarsakan(PendaftaranSertifikasi $pendaftaran, Pembayaran $pembayaran): void
    {
        DB::transaction(function () use ($pendaftaran, $pembayaran) {
            $statusSebelumnya = $pendaftaran->status;
            $pembayaran->update(['status' => 'expired']);
            $pendaftaran->update(['status' => 'expired']);
            $pendaftaran->riwayatStatus()->create([
                'status_dari' => $statusSebelumnya,
                'status_ke' => 'expired',
                'keterangan' => 'Batas waktu pembayaran telah lewat.',
            ]);
        });
    }
}
