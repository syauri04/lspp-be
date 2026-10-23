<?php

namespace App\Services\Muk;

use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\PenilaianMuk;
use App\Models\RiwayatDokumenPenilaian;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Penentuan metode asesmen (KT / TPD / VP) oleh asesor.
 *
 * Syarat: FR.APL.01 diterima admin & FR.APL.02 ditinjau asesor "dapat dilanjutkan".
 * Saat ditetapkan, dokumen turunan metode (MAPA, AK, IA; lihat jenis_dokumen_metode) langsung dibuat.
 * Metode masih boleh diganti selama SEMUA dokumen turunan berstatus belum_diisi
 * (dokumen lama dihapus lalu dibuat ulang sesuai metode baru).
 */
class MetodeAsesmenService
{
    /** @return string|null alasan tidak bisa menentukan/mengubah metode, atau null bila boleh */
    public function alasanTidakBisa(PenilaianMuk $penilaian, ?Collection $dokumen = null): ?string
    {
        $dokumen ??= $penilaian->dokumenPenilaian()->with('jenisDokumen')->get();

        if (in_array($penilaian->status, PenilaianMuk::STATUS_SELESAI, true)) {
            return 'Penilaian sudah selesai.';
        }
        if (! AksesDokumen::apl01Diterima($dokumen)) {
            return 'Menunggu FR.APL.01 diterima admin LSP.';
        }

        $apl02 = $dokumen->first(fn (DokumenPenilaian $d) => $d->jenisDokumen->kode === JenisDokumenMuk::APL_02);
        if ($apl02?->status !== DokumenPenilaian::STATUS_SELESAI || $apl02->hasil_rekomendasi_asesor !== 'dapat_dilanjutkan') {
            return 'Tinjau FR.APL.02 dengan rekomendasi "dapat dilanjutkan" terlebih dahulu.';
        }

        // Dokumen turunan dibuat saat metode ditetapkan; metode masih boleh diganti
        // selama SEMUA dokumen turunan belum diisi.
        $turunanSudahDiisi = $this->turunan($dokumen)->contains(fn (DokumenPenilaian $d) => $d->status !== DokumenPenilaian::STATUS_BELUM_DIISI);
        if ($turunanSudahDiisi) {
            return 'Metode tidak dapat diubah karena dokumen asesmen sudah mulai diisi.';
        }

        return null;
    }

    /** @return Collection<int, DokumenPenilaian> dokumen selain APL (MAPA, AK, IA) */
    private function turunan(Collection $dokumen): Collection
    {
        return $dokumen->reject(fn (DokumenPenilaian $d) => in_array($d->jenisDokumen->kode, JenisDokumenMuk::APL, true));
    }

    /** Buat ulang dokumen turunan sesuai metode (dipanggil di dalam transaksi & lock). */
    private function buatDokumenMetode(PenilaianMuk $p, Collection $dokumen, string $metode): void
    {
        $this->turunan($dokumen)->each(fn (DokumenPenilaian $d) => $d->delete());

        JenisDokumenMuk::query()->untukMetode($metode)->get()->each(
            fn (JenisDokumenMuk $jenis) => DokumenPenilaian::create([
                'penilaian_muk_id' => $p->id,
                'jenis_dokumen_muk_id' => $jenis->id,
                'status' => DokumenPenilaian::STATUS_BELUM_DIISI,
            ])
        );
    }

    public function tetapkan(PenilaianMuk $penilaian, string $metode, Asesor $asesor): PenilaianMuk
    {
        return DB::transaction(function () use ($penilaian, $metode, $asesor) {
            $p = PenilaianMuk::query()->lockForUpdate()->findOrFail($penilaian->id);
            $dokumen = $p->dokumenPenilaian()->with('jenisDokumen')->get();

            if ($alasan = $this->alasanTidakBisa($p, $dokumen)) {
                throw new ConflictHttpException($alasan);
            }

            $sebelumnya = $p->metode_asesmen;
            // Juga untuk penilaian lama yang metodenya ditetapkan sebelum dokumen turunan ada.
            if ($sebelumnya !== $metode || $this->turunan($dokumen)->isEmpty()) {
                $this->buatDokumenMetode($p, $dokumen, $metode);
            }
            $p->update([
                'metode_asesmen' => $metode,
                'ditentukan_oleh_asesor_id' => $asesor->id,
                'metode_ditentukan_at' => now(),
                'status' => PenilaianMuk::STATUS_BERLANGSUNG,
            ]);
            // Jadwal asesmen di CMS menjadi "berlangsung".
            app(SinkronStatusAsesmen::class)->mulai($p);

            // Dicatat pada riwayat FR.APL.02 (dokumen yang menjadi dasar keputusan metode).
            $apl02 = $dokumen->first(fn (DokumenPenilaian $d) => $d->jenisDokumen->kode === JenisDokumenMuk::APL_02);
            RiwayatDokumenPenilaian::create([
                'dokumen_penilaian_id' => $apl02->id,
                'aksi' => $sebelumnya ? 'metode_diubah' : 'metode_ditentukan',
                'status_dari' => $apl02->status,
                'status_ke' => $apl02->status,
                'pelaku_type' => $asesor->getMorphClass(),
                'pelaku_id' => $asesor->id,
                'meta' => ['metode' => $metode, 'sebelumnya' => $sebelumnya],
            ]);

            return $p;
        });
    }
}
