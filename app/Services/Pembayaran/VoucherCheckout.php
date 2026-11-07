<?php

namespace App\Services\Pembayaran;

use App\Models\Asesi;
use App\Models\BuktiPembayaran;
use App\Models\Pembayaran;
use App\Models\Voucher;
use App\Models\VoucherPemakaian;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pasang / lepas voucher pada tagihan (checkout asesi).
 *
 * Keamanan & konsistensi:
 * - Semua perhitungan di server (FE hanya mengirim kode). Rupiah dihitung dalam bilangan bulat.
 * - Dalam satu transaksi dengan penguncian baris berurutan: tagihan -> voucher, sehingga klik ganda
 *   dan banyak asesi yang memakai voucher berkuota bersamaan tidak bisa melebihi kuota.
 * - Kuota & batas per asesi hanya menghitung pemakaian yang masih terpasang pada tagihan pending/lunas,
 *   jadi tagihan yang kedaluwarsa otomatis mengembalikan kuota tanpa job tambahan.
 * - Voucher tidak bisa diubah setelah sesi DOKU dibuat atau saat bukti transfer diverifikasi, agar nominal
 *   yang dibayar selalu sama dengan tagihan.
 * - Potongan 100% (tagihan menjadi Rp 0) langsung dinyatakan lunas dengan metode VOUCHER.
 */
class VoucherCheckout
{
    public const METODE_GRATIS = 'VOUCHER';

    public function __construct(private KonfirmasiPembayaran $konfirmasi) {}

    /** @return Pembayaran tagihan terbaru setelah voucher dipasang */
    public function pasang(Pembayaran $pembayaran, Asesi $asesi, string $kodeMasukan): Pembayaran
    {
        $kode = Voucher::normalisasiKode($kodeMasukan);

        return DB::transaction(function () use ($pembayaran, $asesi, $kode) {
            $tagihan = $this->kunciTagihan($pembayaran);

            $voucher = Voucher::query()->where('kode', $kode)->lockForUpdate()->first();
            if (! $voucher || ! $voucher->aktif) {
                $this->gagal('Kode voucher tidak valid.');
            }
            if (now()->lt($voucher->mulai_at)) {
                $this->gagal('Voucher belum dapat digunakan. Berlaku mulai '.$this->tanggal($voucher->mulai_at).'.');
            }
            if (now()->gt($voucher->berakhir_at)) {
                $this->gagal('Voucher sudah kedaluwarsa.');
            }

            $pemakaianLama = $tagihan->voucherPemakaian;
            if ($pemakaianLama && $pemakaianLama->voucher_id === $voucher->id) {
                $this->gagal('Voucher ini sudah terpasang pada tagihan Anda.');
            }

            // Hitung pemakaian lain (tagihan ini dikecualikan karena vouchernya akan diganti).
            $terpakai = fn () => $voucher->pemakaianTerpakai()->where('pembayaran_id', '!=', $tagihan->id);
            if ($voucher->kuota !== null && $terpakai()->count() >= $voucher->kuota) {
                $this->gagal('Kuota voucher sudah habis.');
            }
            if ($voucher->maks_per_asesi !== null && $terpakai()->where('asesi_id', $asesi->id)->count() >= $voucher->maks_per_asesi) {
                $this->gagal('Anda sudah mencapai batas pemakaian voucher ini.');
            }

            $hargaAwal = $tagihan->hargaAwal();
            $potongan = $voucher->hitungPotongan($hargaAwal);
            if ($potongan <= 0) {
                $this->gagal('Voucher tidak memberikan potongan untuk tagihan ini.');
            }

            $this->lepasPemakaian($pemakaianLama);

            VoucherPemakaian::create([
                'voucher_id' => $voucher->id,
                'pembayaran_id' => $tagihan->id,
                'asesi_id' => $asesi->id,
                'skema_sertifikasi_id' => $tagihan->pendaftaranSertifikasi->skema_sertifikasi_id,
                'kode' => $voucher->kode,
                'jumlah_awal' => $hargaAwal,
                'potongan' => $potongan,
                'jumlah_akhir' => $hargaAwal - $potongan,
                'aktif' => true,
            ]);

            $tagihan->update([
                'jumlah_awal' => $hargaAwal,
                'potongan' => $potongan,
                'jumlah' => $hargaAwal - $potongan,
                'voucher_id' => $voucher->id,
                'voucher_kode' => $voucher->kode,
            ]);

            if ($hargaAwal - $potongan === 0) {
                $this->konfirmasi->lunas(
                    $tagihan,
                    "Lunas dengan voucher {$voucher->kode} (potongan 100%).",
                    self::METODE_GRATIS,
                );
            }

            return $tagihan->fresh();
        });
    }

    public function lepas(Pembayaran $pembayaran): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran) {
            $tagihan = $this->kunciTagihan($pembayaran);
            $pemakaian = $tagihan->voucherPemakaian;
            if (! $pemakaian) {
                $this->gagal('Tidak ada voucher yang terpasang.');
            }

            $this->lepasPemakaian($pemakaian);
            $tagihan->update([
                'jumlah' => $tagihan->hargaAwal(),
                'jumlah_awal' => $tagihan->hargaAwal(),
                'potongan' => 0,
                'voucher_id' => null,
                'voucher_kode' => null,
            ]);

            return $tagihan->fresh();
        });
    }

    /** Alasan voucher tidak bisa diubah (null = bisa). Dipakai juga untuk ditampilkan di checkout. */
    public static function alasanTerkunci(Pembayaran $tagihan): ?string
    {
        return match (true) {
            $tagihan->status !== 'pending' => 'Tagihan ini tidak lagi menunggu pembayaran.',
            $tagihan->expired_at !== null && now()->greaterThanOrEqualTo($tagihan->expired_at) => 'Batas waktu pembayaran sudah lewat.',
            $tagihan->buktiPembayaran()->where('status', BuktiPembayaran::MENUNGGU)->exists() => 'Voucher tidak dapat diubah selama bukti transfer sedang diverifikasi admin.',
            filled(data_get($tagihan->gateway_payload, 'response.payment.url')) => 'Voucher tidak dapat diubah karena sesi pembayaran online (DOKU) sudah dibuat. Hubungi admin LSP untuk mereset sesi pembayaran agar voucher dapat digunakan.',
            default => null,
        };
    }

    private function kunciTagihan(Pembayaran $pembayaran): Pembayaran
    {
        $tagihan = Pembayaran::query()->lockForUpdate()->findOrFail($pembayaran->id);
        if ($alasan = self::alasanTerkunci($tagihan)) {
            $this->gagal($alasan);
        }

        return $tagihan;
    }

    private function lepasPemakaian(?VoucherPemakaian $pemakaian): void
    {
        $pemakaian?->update(['aktif' => null, 'dilepas_at' => now()]);
    }

    private function tanggal($waktu): string
    {
        return $waktu->timezone(config('muk.zona_waktu'))->locale('id')->translatedFormat('j F Y H:i').' WIB';
    }

    private function gagal(string $pesan): never
    {
        throw ValidationException::withMessages(['kode' => [$pesan]]);
    }
}
