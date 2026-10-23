<?php

namespace App\Services\Muk;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\User;
use App\Notifications\Muk\DokumenDitinjauNotifikasi;
use App\Notifications\Muk\DokumenPersetujuanNotifikasi;
use App\Notifications\Muk\DokumenSiapDitinjauNotifikasi;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi alur dokumen MUK. Dipanggil DokumenWorkflow SETELAH transaksi commit.
 * Kegagalan menyusun notifikasi tidak boleh menggagalkan aksi utama (dicatat ke log saja);
 * pengiriman sendiri berjalan di queue.
 */
class NotifikasiMuk
{
    /** Asesi mengirim dokumen. */
    public function setelahDikirim(DokumenPenilaian $dokumen): void
    {
        $this->aman(function () use ($dokumen) {
            $kode = $dokumen->jenisDokumen->kode;

            if ($kode === JenisDokumenMuk::APL_01) {
                $this->kirimKeAdmin($dokumen);
            } elseif ($kode === JenisDokumenMuk::APL_02 && $this->apl01Diterima($dokumen)) {
                $this->kirimKeAsesor($dokumen);
            } elseif ($dokumen->status === DokumenPenilaian::STATUS_MENUNGGU_PERSETUJUAN) {
                // Dokumen asesor (AK.07/AK.01) menunggu tanda tangan asesi.
                $dokumen->penilaianMuk->pendaftaran->asesi
                    ?->notify(new DokumenPersetujuanNotifikasi($dokumen, DokumenPersetujuanNotifikasi::MINTA));
            } elseif ($kode === JenisDokumenMuk::IA_06A
                || ($dokumen->status === DokumenPenilaian::STATUS_MENUNGGU_REVIEW && ! in_array($kode, JenisDokumenMuk::APL, true))) {
                // Jawaban asesi (mis. FR.IA.03, IA.06A) siap dinilai asesor.
                $dokumen->penilaianMuk->jadwalAsesmen?->asesor
                    ?->notify(new DokumenPersetujuanNotifikasi($dokumen, DokumenPersetujuanNotifikasi::DIJAWAB));
            }
        });
    }

    /** Asesor membuka dokumen untuk dijawab asesi (mis. FR.IA.03). */
    public function setelahDibuka(DokumenPenilaian $dokumen): void
    {
        $this->aman(fn () => $dokumen->penilaianMuk->pendaftaran->asesi
            ?->notify(new DokumenPersetujuanNotifikasi($dokumen, DokumenPersetujuanNotifikasi::JAWAB)));
    }

    /** Asesor selesai menilai jawaban asesi (mis. FR.IA.03). */
    public function setelahDinilai(DokumenPenilaian $dokumen): void
    {
        $this->aman(fn () => $dokumen->penilaianMuk->pendaftaran->asesi
            ?->notify(new DokumenPersetujuanNotifikasi($dokumen, DokumenPersetujuanNotifikasi::DINILAI)));
    }

    /** Pihak kedua menyetujui dokumen (mis. asesi atas AK.07/AK.01) -> kabari pengisi (asesor). */
    public function setelahDisetujui(DokumenPenilaian $dokumen, string $pihak): void
    {
        $this->aman(function () use ($dokumen, $pihak) {
            if ($pihak === 'asesi') {
                $dokumen->penilaianMuk->jadwalAsesmen?->asesor
                    ?->notify(new DokumenPersetujuanNotifikasi($dokumen, DokumenPersetujuanNotifikasi::DISETUJUI));
            }
        });
    }

    /** Peninjau memutuskan dokumen. */
    public function setelahDitinjau(DokumenPenilaian $dokumen, string $pihak, bool $positif): void
    {
        $this->aman(function () use ($dokumen, $pihak, $positif) {
            $asesi = $dokumen->penilaianMuk->pendaftaran->asesi;
            $asesi?->notify(new DokumenDitinjauNotifikasi($dokumen, $positif, $pihak === 'admin' ? 'admin LSP' : 'asesor'));

            // APL.01 baru diterima & APL.02 sudah menunggu -> asesor kini bisa meninjau APL.02.
            if ($pihak === 'admin' && $positif && $dokumen->jenisDokumen->kode === JenisDokumenMuk::APL_01) {
                $apl02 = $dokumen->penilaianMuk->dokumenPenilaian()->kode(JenisDokumenMuk::APL_02)->first();
                if ($apl02?->status === DokumenPenilaian::STATUS_MENUNGGU_REVIEW) {
                    $this->kirimKeAsesor($apl02);
                }
            }
        });
    }

    private function kirimKeAdmin(DokumenPenilaian $dokumen): void
    {
        $url = route('review-apl01.show', $dokumen);

        // whereHas (bukan User::role()) agar tidak error bila salah satu role belum dibuat.
        User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super-admin']))->get()->each(
            fn (User $admin) => $admin->notify(new DokumenSiapDitinjauNotifikasi($dokumen, $url, denganDatabase: true))
        );
    }

    private function kirimKeAsesor(DokumenPenilaian $dokumen): void
    {
        $asesor = $dokumen->penilaianMuk->jadwalAsesmen?->asesor;
        $url = config('muk.frontend_url').'/penilaian/'.$dokumen->penilaian_muk_id;

        $asesor?->notify(new DokumenSiapDitinjauNotifikasi($dokumen, $url));
    }

    private function apl01Diterima(DokumenPenilaian $dokumen): bool
    {
        return AksesDokumen::apl01Diterima($dokumen->penilaianMuk->dokumenPenilaian()->with('jenisDokumen')->get());
    }

    private function aman(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::error('Gagal menyiapkan notifikasi MUK', ['error' => $e->getMessage()]);
        }
    }
}
