<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\MapaTemplate;
use App\Models\SkemaSertifikasi;
use App\Services\Muk\TandaTanganService;
use Illuminate\Support\Facades\Storage;

/**
 * Dasar FR.MAPA.01 / FR.MAPA.02: diisi ASESOR, tanpa peninjau & tanpa tanda tangan asesor.
 * Bagian template (rencana §2, konfirmasi, penyusun & validator) berasal dari CMS per
 * skema + metode; saat diselesaikan, template dibekukan ke snapshot beserta SALINAN
 * gambar tanda tangan agar perubahan template kemudian tidak mengubah dokumen.
 */
abstract class MapaHandler implements DokumenHandler
{
    public function __construct(protected TandaTanganService $ttd) {}

    public function pengisi(): string
    {
        return 'asesor';
    }

    public function peninjau(): ?string
    {
        return null;
    }

    public function butuhTandaTangan(): bool
    {
        return false;
    }

    protected function skema(DokumenPenilaian $dokumen): SkemaSertifikasi
    {
        return SkemaSertifikasi::with('unitKompetensis')->findOrFail($dokumen->penilaianMuk->pendaftaran->skema_sertifikasi_id);
    }

    protected function template(DokumenPenilaian $dokumen): ?MapaTemplate
    {
        $metode = $dokumen->penilaianMuk->metode_asesmen;

        return $metode
            ? MapaTemplate::with(['rencana', 'penandatangan'])
                ->where('skema_sertifikasi_id', $dokumen->penilaianMuk->pendaftaran->skema_sertifikasi_id)
                ->where('metode', $metode)
                ->first()
            : null;
    }

    protected function infoSkema(SkemaSertifikasi $skema): array
    {
        return [
            'id' => $skema->id,
            'judul' => $skema->title['id'] ?? null,
            'nomor' => $skema->nomor_skema,
            'jenis' => $skema->jenis_skema,
        ];
    }

    protected function daftarUnit(SkemaSertifikasi $skema): array
    {
        return $skema->unitKompetensis->map(fn ($u) => [
            'id' => $u->id,
            'kode_unit' => $u->kode_unit,
            'judul_unit' => $u->judul_unit,
        ])->values()->all();
    }

    /**
     * Penandatangan template untuk bagian tsb (tanpa path file).
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
                'tanggal' => $p->tanggal?->toDateString(),
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
     * Salin gambar tanda tangan template ke folder dokumen (dipakai saat snapshot).
     *
     * @return array<int, array<string, mixed>> baris dengan _path menunjuk salinan
     */
    protected function salinTtd(DokumenPenilaian $dokumen, array $baris): array
    {
        $disk = Storage::disk($this->ttd->disk());
        $slug = $dokumen->jenisDokumen->slug;

        return array_map(function (array $b) use ($disk, $dokumen, $slug) {
            if ($b['_path'] && $disk->exists($b['_path'])) {
                $salinan = "signatures/{$dokumen->penilaian_muk_id}/{$slug}/penandatangan-{$b['id']}.png";
                $disk->put($salinan, $disk->get($b['_path']));
                $b['_path'] = $salinan;
            } else {
                $b['_path'] = null;
                $b['ada_ttd'] = false;
            }

            return $b;
        }, $baris);
    }

    /** Path gambar tanda tangan penandatangan (dari snapshot bila sudah selesai, selain itu template). */
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

        return $this->template($dokumen)?->penandatangan->firstWhere('id', $id)?->path_ttd;
    }
}
