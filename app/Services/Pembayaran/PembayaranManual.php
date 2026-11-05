<?php

namespace App\Services\Pembayaran;

use App\Models\BuktiPembayaran;
use App\Models\Pembayaran;
use App\Models\PendaftaranSertifikasi;
use App\Models\Pengaturan;
use App\Models\RekeningPembayaran;
use App\Models\User;
use App\Notifications\BuktiPembayaranDitolakNotifikasi;
use App\Notifications\BuktiPembayaranMasukNotifikasi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Pembayaran manual (transfer bank):
 *  1. Asesi mengunggah bukti transfer -> bukti "menunggu"; pendaftaran tetap "awaiting_payment".
 *  2. Admin memverifikasi di CMS:
 *     - setujui -> KonfirmasiPembayaran::lunas() (status paid, riwayat, notifikasi "Pembayaran berhasil"),
 *       sehingga pendaftaran bisa dijadwalkan asesmen (MUK) seperti pembayaran DOKU;
 *     - tolak   -> bukti "ditolak" + alasan, asesi dikabari dan dapat mengunggah ulang.
 * Semua perubahan status dilakukan di dalam transaksi dengan penguncian baris (aman dari klik ganda).
 */
class PembayaranManual
{
    public function __construct(private KonfirmasiPembayaran $konfirmasi) {}

    /** @param array{rekening_pembayaran_id:int, nama_pengirim:string, bank_pengirim:string, tanggal_transfer:string, jumlah_transfer:numeric, catatan?:?string} $data */
    public function kirimBukti(PendaftaranSertifikasi $pendaftaran, array $data, UploadedFile $file): BuktiPembayaran
    {
        if (! Pengaturan::aktif(Pengaturan::PEMBAYARAN_MANUAL_AKTIF)) {
            throw new ConflictHttpException('Pembayaran transfer manual sedang tidak tersedia.');
        }

        // Berkas ditulis lebih dulu (di luar transaksi), dihapus lagi bila transaksi gagal.
        $ext = $file->guessExtension() ?: 'bin';
        $path = $file->storeAs("bukti-pembayaran/{$pendaftaran->kode_pendaftaran}", Str::uuid().".{$ext}", BuktiPembayaran::disk());

        try {
            $bukti = DB::transaction(function () use ($pendaftaran, $data, $file, $path) {
                $pembayaran = Pembayaran::query()->where('pendaftaran_sertifikasi_id', $pendaftaran->id)->lockForUpdate()->first();
                $this->pastikanBisaDibayar($pendaftaran->fresh(), $pembayaran);
                if ($pembayaran->buktiPembayaran()->where('status', BuktiPembayaran::MENUNGGU)->exists()) {
                    throw new ConflictHttpException('Bukti transfer sebelumnya masih diverifikasi admin.');
                }

                $rekening = RekeningPembayaran::query()->aktif()->findOrFail($data['rekening_pembayaran_id']);

                $bukti = $pembayaran->buktiPembayaran()->create([
                    'rekening_pembayaran_id' => $rekening->id,
                    'rekening_tujuan' => $rekening->snapshot(),
                    'nama_pengirim' => trim($data['nama_pengirim']),
                    'bank_pengirim' => trim($data['bank_pengirim']),
                    'tanggal_transfer' => $data['tanggal_transfer'],
                    'jumlah_transfer' => $data['jumlah_transfer'],
                    'catatan_asesi' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
                    'path_file' => $path,
                    'nama_file_asli' => Str::limit($file->getClientOriginalName(), 200, ''),
                    'mime_type' => $file->getMimeType(),
                    'ukuran_file' => (int) ceil($file->getSize() / 1024),
                    'status' => BuktiPembayaran::MENUNGGU,
                ]);

                // Tercatat di timeline (status tetap "menunggu pembayaran").
                $pendaftaran->riwayatStatus()->create([
                    'status_dari' => 'awaiting_payment',
                    'status_ke' => 'awaiting_payment',
                    'keterangan' => "Bukti transfer ke {$rekening->nama_bank} diunggah, menunggu verifikasi admin.",
                ]);

                return $bukti;
            });
        } catch (\Throwable $e) {
            Storage::disk(BuktiPembayaran::disk())->delete($path);
            throw $e;
        }

        $this->kabariAdmin($bukti);

        return $bukti;
    }

    /** Admin menyetujui bukti -> tagihan lunas (alur sama dengan DOKU). */
    public function setujui(BuktiPembayaran $bukti, User $admin, ?string $catatan = null): void
    {
        DB::transaction(function () use ($bukti, $admin, $catatan) {
            $b = BuktiPembayaran::query()->lockForUpdate()->findOrFail($bukti->id);
            $this->pastikanMenunggu($b);
            $this->pastikanBisaDibayar($b->pembayaran->pendaftaranSertifikasi, $b->pembayaran);

            $b->update([
                'status' => BuktiPembayaran::DISETUJUI,
                'catatan_admin' => filled($catatan) ? trim($catatan) : null,
                'diverifikasi_oleh' => $admin->id,
                'diverifikasi_at' => now(),
            ]);

            $lunas = $this->konfirmasi->lunas(
                $b->pembayaran,
                "Pembayaran transfer manual diverifikasi admin (dari {$b->bank_pengirim} a.n. {$b->nama_pengirim}).",
                BuktiPembayaran::METODE,
                diubahOleh: $admin->id,
            );
            if (! $lunas) {
                throw new ConflictHttpException('Tagihan ini sudah lunas.');
            }
        });
    }

    /** Admin menolak bukti; asesi dapat mengunggah ulang. */
    public function tolak(BuktiPembayaran $bukti, User $admin, string $alasan): void
    {
        $b = DB::transaction(function () use ($bukti, $admin, $alasan) {
            $b = BuktiPembayaran::query()->lockForUpdate()->findOrFail($bukti->id);
            $this->pastikanMenunggu($b);

            $b->update([
                'status' => BuktiPembayaran::DITOLAK,
                'catatan_admin' => trim($alasan),
                'diverifikasi_oleh' => $admin->id,
                'diverifikasi_at' => now(),
            ]);

            $b->pembayaran->pendaftaranSertifikasi->riwayatStatus()->create([
                'status_dari' => 'awaiting_payment',
                'status_ke' => 'awaiting_payment',
                'keterangan' => 'Bukti transfer ditolak admin: '.trim($alasan),
                'changed_by' => $admin->id,
            ]);

            return $b;
        });

        try {
            $b->pembayaran->pendaftaranSertifikasi->asesi?->notify(new BuktiPembayaranDitolakNotifikasi($b));
        } catch (\Throwable $e) {
            Log::error('Gagal menyusun notifikasi bukti ditolak', ['bukti_id' => $b->id, 'error' => $e->getMessage()]);
        }
    }

    private function pastikanBisaDibayar(?PendaftaranSertifikasi $pendaftaran, ?Pembayaran $pembayaran): void
    {
        if (! $pendaftaran || $pendaftaran->status !== 'awaiting_payment' || ! $pembayaran || $pembayaran->status !== 'pending') {
            throw new ConflictHttpException('Pendaftaran ini tidak dalam status menunggu pembayaran.');
        }
    }

    private function pastikanMenunggu(BuktiPembayaran $bukti): void
    {
        if ($bukti->status !== BuktiPembayaran::MENUNGGU) {
            throw new ConflictHttpException('Bukti transfer ini sudah diverifikasi sebelumnya.');
        }
    }

    private function kabariAdmin(BuktiPembayaran $bukti): void
    {
        try {
            // whereHas (bukan User::role()) agar tidak error bila salah satu role belum dibuat.
            User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super-admin']))->get()
                ->each(fn (User $admin) => $admin->notify(new BuktiPembayaranMasukNotifikasi($bukti)));
        } catch (\Throwable $e) {
            Log::error('Gagal menyusun notifikasi bukti pembayaran masuk', ['bukti_id' => $bukti->id, 'error' => $e->getMessage()]);
        }
    }
}
