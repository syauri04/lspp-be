<?php

namespace App\Services\Muk;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use Illuminate\Support\Collection;

/**
 * Aturan "dokumen ini boleh dibuka oleh peran X atau tidak" (menu sidebar & guard endpoint).
 * Satu tempat agar FE (flag terkunci) dan endpoint dokumen (Fase 4+) memakai aturan yang sama.
 */
class AksesDokumen
{
    /**
     * @param  Collection<int, DokumenPenilaian>  $semuaDokumen  dokumen lain dalam penilaian yang sama (jenisDokumen sudah di-load)
     * @return string|null alasan terkunci, atau null bila boleh dibuka
     */
    public function alasanTerkunci(DokumenPenilaian $dokumen, Collection $semuaDokumen, string $peran): ?string
    {
        $kode = $dokumen->jenisDokumen->kode;
        $kategori = $dokumen->jenisDokumen->kategori;

        // MAPA diisi asesor (bukan asesi).
        if ($kategori === 'MAPA') {
            return $peran === 'asesor' ? null : 'Dokumen ini diisi oleh asesor.';
        }

        // Dokumen AK/IA terbuka setelah MAPA.01 & MAPA.02 diselesaikan asesor.
        if (in_array($kategori, ['AK', 'IA'], true) && ! self::mapaSelesai($semuaDokumen)) {
            return 'Menunggu FR.MAPA.01 dan FR.MAPA.02 diselesaikan asesor.';
        }
        if (in_array($kategori, ['AK', 'IA'], true)) {
            return null;
        }

        if ($peran === 'asesi') {
            if ($kode === JenisDokumenMuk::APL_02) {
                $apl01 = $semuaDokumen->first(fn (DokumenPenilaian $d) => $d->jenisDokumen->kode === JenisDokumenMuk::APL_01);

                // APL.01 yang dikembalikan admin untuk revisi tetap membuka APL.02.
                $dikembalikan = $apl01?->hasil_rekomendasi_admin === 'ditolak';

                if (! $apl01 || (! $dikembalikan && ! in_array($apl01->status, config('muk.apl02_butuh_status_apl01'), true))) {
                    return 'Kirim FR.APL.01 terlebih dahulu.';
                }
            }

            return null;
        }

        // Asesor hanya membaca dokumen yang sudah dikirim asesi.
        if (! in_array($dokumen->status, DokumenPenilaian::STATUS_TERKIRIM, true)) {
            return 'Asesi belum mengirim dokumen ini.';
        }

        return null;
    }

    /**
     * Apakah peran tsb boleh meninjau (review + tanda tangan keputusan) dokumen ini sekarang.
     * FR.APL.02 baru bisa ditinjau asesor setelah FR.APL.01 DITERIMA admin LSP.
     *
     * @param  Collection<int, DokumenPenilaian>  $semuaDokumen
     * @return string|null alasan tidak bisa, atau null bila boleh
     */
    public function alasanTidakBisaDireview(DokumenPenilaian $dokumen, Collection $semuaDokumen, string $peran, ?string $peninjau): ?string
    {
        if ($peninjau === null || $peninjau !== $peran) {
            return 'Anda tidak meninjau dokumen ini.';
        }
        if ($dokumen->status !== DokumenPenilaian::STATUS_MENUNGGU_REVIEW) {
            return $dokumen->status === DokumenPenilaian::STATUS_SELESAI
                ? 'Dokumen sudah ditinjau.'
                : 'Asesi belum mengirim dokumen ini.';
        }

        if ($dokumen->jenisDokumen->kode === JenisDokumenMuk::APL_02 && ! self::apl01Diterima($semuaDokumen)) {
            return 'Menunggu FR.APL.01 diterima admin LSP.';
        }

        return null;
    }

    /** @param  Collection<int, DokumenPenilaian>  $semuaDokumen */
    public static function mapaSelesai(Collection $semuaDokumen): bool
    {
        $mapa = $semuaDokumen->filter(fn (DokumenPenilaian $d) => in_array($d->jenisDokumen->kode, [JenisDokumenMuk::MAPA_01, JenisDokumenMuk::MAPA_02], true));

        return $mapa->count() === 2 && $mapa->every(fn (DokumenPenilaian $d) => $d->status === DokumenPenilaian::STATUS_SELESAI);
    }

    /** @param  Collection<int, DokumenPenilaian>  $semuaDokumen */
    public static function apl01Diterima(Collection $semuaDokumen): bool
    {
        $apl01 = $semuaDokumen->first(fn (DokumenPenilaian $d) => $d->jenisDokumen->kode === JenisDokumenMuk::APL_01);

        return $apl01?->status === DokumenPenilaian::STATUS_SELESAI && $apl01->hasil_rekomendasi_admin === 'diterima';
    }
}
