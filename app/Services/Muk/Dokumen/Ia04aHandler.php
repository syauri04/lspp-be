<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\MapaTemplate;
use App\Services\Muk\TandaTanganService;
use Illuminate\Validation\Rule;

/**
 * FR.IA.04A DIT — Daftar Instruksi Terstruktur (Penjelasan Proyek Singkat/Kegiatan Terstruktur), metode KT.
 * "Hal yang disiapkan" & "hal yang didemonstrasikan" serta penyusun/validator berasal dari template KT
 * (CMS) dan tidak dapat diubah asesor. Asesor mengisi TUK, umpan balik (opsional) & nama supervisor
 * (opsional), menandatangani; asesi menyetujui dengan tanda tangan. Dibekukan saat dikirim.
 *
 * Isian: { tuk, umpan_balik, supervisor }
 */
class Ia04aHandler extends PersetujuanHandler implements PunyaPenandatangan
{
    use MemakaiTemplatePerangkat;

    public function __construct(protected TandaTanganService $ttd) {}

    public function kode(): string
    {
        return JenisDokumenMuk::IA_04A;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        $master = $dokumen->snapshot ?? $this->master($dokumen);

        return [
            ...$this->payloadDasar($dokumen),
            'unit_kompetensi' => $master['unit_kompetensi'],
            'instrumen' => $master['instrumen'],
            'penyusun' => self::tanpaPath($master['penandatangan']['penyusun_ia04a']),
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:tuk,umpan_balik,supervisor'],
            'isian.tuk' => ['nullable', Rule::in(array_keys(self::TUK))],
            'isian.umpan_balik' => ['nullable', 'string', 'max:5000'],
            'isian.supervisor' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function kesalahanSubmit(DokumenPenilaian $dokumen): array
    {
        $errors = [];
        if ($this->normalisasi($dokumen, $dokumen->data)['tuk'] === null) {
            $errors['isian.tuk'] = 'Pilih jenis TUK.';
        }
        $instrumen = $this->master($dokumen)['instrumen'];
        if ($instrumen['hal_disiapkan'] === null || $instrumen['hal_didemonstrasikan'] === null) {
            $errors['instrumen'] = 'Instruksi FR.IA.04A untuk skema ini belum diisi di CMS (Template KT). Hubungi admin LSP.';
        }

        return $errors;
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        $master = $this->master($dokumen);
        $master['penandatangan'] = array_map(fn ($baris) => $this->salinTtd($dokumen, $baris), $master['penandatangan']);

        return [...parent::snapshot($dokumen), ...$master];
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        return $this->normalisasi($dokumen, ['tuk' => $this->tukAwal($dokumen)]);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        return [
            'tuk' => self::tukSah($data['tuk'] ?? null),
            'umpan_balik' => self::teks($data['umpan_balik'] ?? null, 5000),
            'supervisor' => self::teks($data['supervisor'] ?? null, 255),
        ];
    }

    private function master(DokumenPenilaian $dokumen): array
    {
        $template = $this->template($dokumen);
        $instrumen = $template?->instrumenUntuk(MapaTemplate::IA_04A) ?? [];

        return [
            'unit_kompetensi' => $this->unitKompetensi($dokumen),
            'instrumen' => [
                'hal_disiapkan' => self::teks($instrumen['hal_disiapkan'] ?? null, 15000),
                'hal_didemonstrasikan' => self::teks($instrumen['hal_didemonstrasikan'] ?? null, 15000),
                'waktu_pengerjaan' => self::teks($instrumen['waktu_pengerjaan'] ?? null, 1000),
                'waktu_tanya_jawab' => self::teks($instrumen['waktu_tanya_jawab'] ?? null, 1000),
                'total_waktu' => self::teks($instrumen['total_waktu'] ?? null, 1000),
            ],
            'penandatangan' => ['penyusun_ia04a' => $this->penandatangan($template, 'penyusun_ia04a')],
        ];
    }
}
