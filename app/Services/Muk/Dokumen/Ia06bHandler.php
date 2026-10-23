<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\MapaTemplate;
use App\Services\Muk\TandaTanganService;
use Illuminate\Support\Collection;

/**
 * FR.IA.06B Lembar Kunci Jawaban Pertanyaan Tertulis Esai (semua metode) — HANYA ASESOR.
 * Kunci jawaban & penyusun/validator dari template Umum (CMS). Soal mengikuti FR.IA.06A
 * (dibekukan saat IA.06A dibuka). Asesor cukup meninjau lalu "Selesaikan" (tanpa isian &
 * tanpa tanda tangan): isi + salinan ttd penyusun/validator dibekukan di snapshot.
 * Asesi tidak pernah dapat membuka dokumen ini.
 */
class Ia06bHandler extends PersetujuanHandler implements PunyaPenandatangan, PunyaPrasyarat, TanpaIsian
{
    use MemakaiTemplatePerangkat;

    public function __construct(protected TandaTanganService $ttd) {}

    public function kode(): string
    {
        return JenisDokumenMuk::IA_06B;
    }

    public function pengisi(): string
    {
        return 'asesor';
    }

    public function penyetuju(): ?string
    {
        return null;
    }

    public function butuhTandaTangan(): bool
    {
        return false;
    }

    public function alasanPrasyarat(DokumenPenilaian $dokumen, Collection $semuaDokumen, string $peran): ?string
    {
        return $peran === 'asesor' ? null : 'Dokumen ini hanya untuk asesor.';
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        $master = $dokumen->snapshot ?? $this->master($dokumen);

        return [
            'isian' => [],
            'info' => $master['info'] ?? $this->info($dokumen),
            'opsi_tuk' => self::opsi(self::TUK),
            'header' => $master['header'],
            'soal' => $master['soal'],
            'penyusun' => self::tanpaPath($master['penandatangan']['penyusun_ia06b']),
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        // Tidak ada isian yang dapat diubah.
        return ['isian' => ['prohibited']];
    }

    public function simpan(DokumenPenilaian $dokumen, array $validated, array &$hapusNanti): bool
    {
        return false;
    }

    public function kesalahanSubmit(DokumenPenilaian $dokumen): array
    {
        return $this->master($dokumen)['soal'] === []
            ? ['soal' => 'Soal & kunci jawaban FR.IA.06 belum diisi di CMS (Template Umum). Hubungi admin LSP.']
            : [];
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        $master = $this->master($dokumen);
        $master['penandatangan'] = array_map(fn ($baris) => $this->salinTtd($dokumen, $baris), $master['penandatangan']);

        return [...parent::snapshot($dokumen), ...$master];
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        return [];
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        return [];
    }

    protected function templatePenandatangan(DokumenPenilaian $dokumen): ?MapaTemplate
    {
        return $this->templateUmum($dokumen);
    }

    /** Soal & kunci (mengikuti IA.06A), header (TUK/tanggal/waktu IA.06A), penyusun & validator. */
    private function master(DokumenPenilaian $dokumen): array
    {
        $ia06aHandler = app(Ia06aHandler::class);
        $ia06a = $this->dokumenLain($dokumen, JenisDokumenMuk::IA_06A);
        $p = Ia06aHandler::persiapanDari($ia06a, $ia06aHandler->soalTemplate($dokumen));

        return [
            'header' => [
                'tuk' => $p['tuk'],
                'tanggal' => $p['tanggal'],
                'waktu' => $p['waktu'] ?? $ia06aHandler->waktuTemplate($dokumen),
            ],
            'soal' => $p['soal'],
            'penandatangan' => ['penyusun_ia06b' => $this->penandatangan($this->templateUmum($dokumen), 'penyusun_ia06b')],
        ];
    }
}
