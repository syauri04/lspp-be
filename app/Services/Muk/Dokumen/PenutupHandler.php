<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use Illuminate\Support\Collection;

/**
 * Dasar dokumen penutup (FR.AK.03, FR.AK.05, FR.AK.06): terbuka setelah keputusan FR.AK.02 selesai
 * (diisi asesor & disetujui asesi). Tanpa peninjau & tanpa penyetuju: dikirim -> selesai.
 * Header (TUK, tanggal) mengikuti AK.02.
 */
abstract class PenutupHandler extends PersetujuanHandler implements PunyaPrasyarat
{
    public function penyetuju(): ?string
    {
        return null;
    }

    /** Hanya asesor yang dapat membuka (laporan & tinjauan internal LSP). */
    protected function hanyaAsesor(): bool
    {
        return false;
    }

    public function alasanPrasyarat(DokumenPenilaian $dokumen, Collection $semuaDokumen, string $peran): ?string
    {
        if ($this->hanyaAsesor() && $peran !== 'asesor') {
            return 'Dokumen ini hanya untuk asesor.';
        }
        $ak02 = $semuaDokumen->first(fn (DokumenPenilaian $d) => $d->jenisDokumen->kode === JenisDokumenMuk::AK_02);

        return $ak02?->status === DokumenPenilaian::STATUS_SELESAI ? null : 'Menunggu keputusan asesmen (FR.AK.02) selesai.';
    }

    /** Keputusan AK.02 (snapshot dokumen ini bila sudah dikirim). */
    protected function keputusan(DokumenPenilaian $dokumen): array
    {
        return $dokumen->snapshot['keputusan'] ?? Ak02Handler::keputusan($this->dokumenLain($dokumen, JenisDokumenMuk::AK_02));
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        return [...parent::snapshot($dokumen), 'keputusan' => $this->keputusan($dokumen)];
    }
}
