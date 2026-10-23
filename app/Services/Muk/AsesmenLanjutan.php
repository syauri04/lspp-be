<?php

namespace App\Services\Muk;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\PenilaianMuk;

/**
 * Asesmen lanjutan dari rekomendasi FR.IA.08 (metode VP), mengacu alur BNSP:
 * bukti portofolio belum memenuhi -> "OBSERVASI LANGSUNG / KEGIATAN TERSTRUKTUR" hanya pada lingkup
 * (unit / KUK) yang belum terbukti.
 *  - observasi            -> dokumen metode TPD: FR.IA.01, IA.02, IA.03
 *  - kegiatan_terstruktur -> dokumen metode KT : FR.IA.04A, IA.04B
 * Dokumen dibuat saat FR.IA.08 selesai (disetujui asesi). Metode utama penilaian tetap VP.
 * Lingkup di FR.IA.08: lanjutan.unit ["u{id}"], lanjutan.kuk ["k{id}"] (KUK kosong untuk unit = seluruh KUK unit tsb).
 */
class AsesmenLanjutan
{
    public const JENIS = ['observasi' => 'tpd', 'kegiatan_terstruktur' => 'kt'];

    public const DOKUMEN = [
        'tpd' => [JenisDokumenMuk::IA_01, JenisDokumenMuk::IA_02, JenisDokumenMuk::IA_03],
        'kt' => [JenisDokumenMuk::IA_04A, JenisDokumenMuk::IA_04B],
    ];

    /**
     * Rekomendasi lanjutan dari FR.IA.08 yang sudah selesai.
     *
     * @return array{metode: string, unit: int[], kuk: int[]}|null
     */
    public static function dariPenilaian(PenilaianMuk $penilaian): ?array
    {
        if ($penilaian->metode_asesmen !== 'vp') {
            return null;
        }
        $ia08 = $penilaian->dokumenPenilaian()->kode(JenisDokumenMuk::IA_08)->first();
        if ($ia08?->status !== DokumenPenilaian::STATUS_SELESAI || ($ia08->data['rekomendasi'] ?? null) !== 'lanjutan') {
            return null;
        }
        $lanjutan = (array) ($ia08->data['lanjutan'] ?? []);
        $metode = self::JENIS[$lanjutan['jenis'] ?? ''] ?? null;
        if ($metode === null) {
            return null;
        }
        $id = fn (array $kunci, string $awalan) => array_values(array_filter(array_map(
            fn ($k) => is_string($k) && str_starts_with($k, $awalan) ? (int) substr($k, 1) : 0,
            $kunci,
        )));

        return ['metode' => $metode, 'unit' => $id((array) ($lanjutan['unit'] ?? []), 'u'), 'kuk' => $id((array) ($lanjutan['kuk'] ?? []), 'k')];
    }

    /**
     * Lingkup untuk dokumen tsb bila dokumen itu dokumen asesmen lanjutan (selain itu null).
     *
     * @return array{metode: string, unit: int[], kuk: int[]}|null
     */
    public static function untukDokumen(DokumenPenilaian $dokumen): ?array
    {
        $kode = $dokumen->jenisDokumen->kode;
        if (! in_array($kode, [...self::DOKUMEN['tpd'], ...self::DOKUMEN['kt']], true)) {
            return null;
        }
        $lingkup = self::dariPenilaian($dokumen->penilaianMuk);

        return $lingkup && in_array($kode, self::DOKUMEN[$lingkup['metode']], true) ? $lingkup : null;
    }

    /** Buat dokumen metode lanjutan (belum ada saja). Dipanggil di dalam transaksi workflow. */
    public function terapkan(PenilaianMuk $penilaian): void
    {
        $lingkup = self::dariPenilaian($penilaian);
        if ($lingkup === null) {
            return;
        }
        JenisDokumenMuk::whereIn('kode', self::DOKUMEN[$lingkup['metode']])->get()->each(
            fn (JenisDokumenMuk $jenis) => DokumenPenilaian::firstOrCreate(
                ['penilaian_muk_id' => $penilaian->id, 'jenis_dokumen_muk_id' => $jenis->id],
                ['status' => DokumenPenilaian::STATUS_BELUM_DIISI],
            ),
        );
    }
}
