<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\MapaTemplate;
use Illuminate\Support\Facades\Storage;

/**
 * Akses template perangkat asesmen (CMS, per skema + metode penilaian) beserta penandatangannya.
 * Saat dokumen dikirim, gambar tanda tangan template DISALIN ke folder dokumen (snapshot)
 * agar perubahan template kemudian tidak mengubah dokumen.
 *
 * Kelas pemakai wajib punya properti $ttd (TandaTanganService).
 */
trait MemakaiTemplatePerangkat
{
    /** Template "umum" skema (berlaku semua metode), mis. soal esai FR.IA.06. */
    protected function templateUmum(DokumenPenilaian $dokumen): ?MapaTemplate
    {
        return MapaTemplate::with('penandatangan')
            ->where('skema_sertifikasi_id', $dokumen->penilaianMuk->pendaftaran->skema_sertifikasi_id)
            ->where('metode', MapaTemplate::UMUM)
            ->first();
    }

    /** Template sumber penandatangan dokumen ini (default: template metode penilaian). */
    protected function templatePenandatangan(DokumenPenilaian $dokumen): ?MapaTemplate
    {
        return $this->template($dokumen);
    }

    /**
     * Metode template untuk dokumen instrumen tertentu. Dokumen asesmen lanjutan (mis. IA.02 pada penilaian VP)
     * tetap memakai template metodenya sendiri, bukan metode utama penilaian.
     */
    private const METODE_TEMPLATE_DOKUMEN = [
        JenisDokumenMuk::IA_02 => 'tpd',
        JenisDokumenMuk::IA_03 => 'tpd',
        JenisDokumenMuk::IA_04A => 'kt',
        JenisDokumenMuk::IA_04B => 'kt',
        JenisDokumenMuk::IA_09 => 'vp',
    ];

    protected function template(DokumenPenilaian $dokumen): ?MapaTemplate
    {
        $metode = self::METODE_TEMPLATE_DOKUMEN[$dokumen->jenisDokumen?->kode] ?? $dokumen->penilaianMuk->metode_asesmen;

        return $metode
            ? MapaTemplate::with(['rencana', 'penandatangan'])
                ->where('skema_sertifikasi_id', $dokumen->penilaianMuk->pendaftaran->skema_sertifikasi_id)
                ->where('metode', $metode)
                ->first()
            : null;
    }

    /**
     * Penandatangan template untuk bagian tsb (dengan _path internal untuk snapshot).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function penandatangan(?MapaTemplate $template, string $bagian): array
    {
        return ($template?->penandatangan ?? collect())
            ->where('bagian', $bagian)
            ->values()
            ->map(fn ($p) => [
                'id' => $p->id,
                'peran' => $p->peran,
                'nama' => $p->nama,
                'no_reg' => $p->no_reg,
                // Tanggal tidak diisi di CMS: diisi otomatis = tanggal dokumen dikirim asesor (lihat salinTtd).
                'tanggal' => null,
                'ada_ttd' => $p->path_ttd !== null,
                // Hanya untuk snapshot (dihapus dari payload).
                '_path' => $p->path_ttd,
            ])->all();
    }

    /** Buang kunci internal dari baris penandatangan sebelum dikirim ke FE. */
    protected static function tanpaPath(array $baris): array
    {
        return array_map(fn ($b) => array_diff_key($b, ['_path' => true]), $baris);
    }

    /**
     * Salin gambar tanda tangan template ke folder dokumen & isi tanggalnya (dipakai saat snapshot).
     *
     * @return array<int, array<string, mixed>> baris dengan _path menunjuk salinan
     */
    protected function salinTtd(DokumenPenilaian $dokumen, array $baris): array
    {
        $disk = Storage::disk($this->ttd->disk());
        $slug = $dokumen->jenisDokumen->slug;
        $tanggalKirim = now(config('muk.zona_waktu'))->toDateString();

        return array_map(function (array $b) use ($disk, $dokumen, $slug, $tanggalKirim) {
            if ($b['_path'] && $disk->exists($b['_path'])) {
                $salinan = "signatures/{$dokumen->penilaian_muk_id}/{$slug}/penandatangan-{$b['id']}.png";
                $disk->put($salinan, $disk->get($b['_path']));
                $b['_path'] = $salinan;
            } else {
                $b['_path'] = null;
                $b['ada_ttd'] = false;
            }
            // Tanggal tanda tangan penyusun/validator/konfirmasi = tanggal dokumen dikirim.
            $b['tanggal'] = $b['ada_ttd'] ? $tanggalKirim : null;

            return $b;
        }, $baris);
    }

    /** Path gambar tanda tangan penandatangan (dari snapshot bila sudah dikirim, selain itu template). */
    public function pathTtdPenandatangan(DokumenPenilaian $dokumen, int $id): ?string
    {
        if ($dokumen->snapshot) {
            foreach ($dokumen->snapshot['penandatangan'] ?? [] as $baris) {
                foreach ($baris as $b) {
                    if ((int) $b['id'] === $id) {
                        return $b['_path'];
                    }
                }
            }

            return null;
        }

        return $this->templatePenandatangan($dokumen)?->penandatangan->firstWhere('id', $id)?->path_ttd;
    }
}
