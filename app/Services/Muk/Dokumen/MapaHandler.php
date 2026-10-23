<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\SkemaSertifikasi;
use App\Services\Muk\TandaTanganService;

/**
 * Dasar FR.MAPA.01 / FR.MAPA.02: diisi ASESOR, tanpa peninjau & tanpa tanda tangan asesor.
 * Bagian template (rencana §2, konfirmasi, penyusun & validator) berasal dari CMS per
 * skema + metode; saat diselesaikan, template dibekukan ke snapshot beserta SALINAN
 * gambar tanda tangan agar perubahan template kemudian tidak mengubah dokumen.
 */
abstract class MapaHandler implements DokumenHandler, PunyaPenandatangan
{
    use MemakaiTemplatePerangkat;

    public function __construct(protected TandaTanganService $ttd) {}

    public function pengisi(): string
    {
        return 'asesor';
    }

    public function peninjau(): ?string
    {
        return null;
    }

    public function penyetuju(): ?string
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
}
