<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\MapaTemplate;
use App\Services\Muk\TandaTanganService;
use Illuminate\Validation\Rule;

/**
 * FR.IA.02 Tugas Praktik Demonstrasi (metode TPD).
 * Skenario, waktu, serta penyusun & validator berasal dari template perangkat asesmen
 * (CMS, skema + metode TPD) dan tidak dapat diubah asesor. Asesor mengisi TUK & tanggal,
 * menandatangani, lalu asesi menyetujui dengan tanda tangan.
 * Saat dikirim, skenario + salinan tanda tangan penyusun/validator dibekukan di snapshot.
 */
class Ia02Handler extends PersetujuanHandler implements PunyaPenandatangan
{
    use MemakaiTemplatePerangkat;

    public function __construct(protected TandaTanganService $ttd) {}

    public function kode(): string
    {
        return JenisDokumenMuk::IA_02;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        $master = $dokumen->snapshot ?? $this->master($dokumen);

        return [
            ...$this->payloadDasar($dokumen),
            'unit_kompetensi' => $master['unit_kompetensi'],
            'instrumen' => $master['instrumen'],
            'penyusun' => self::tanpaPath($master['penandatangan']['penyusun_ia02']),
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:tuk,tanggal'],
            'isian.tuk' => ['nullable', Rule::in(array_keys(self::TUK))],
            'isian.tanggal' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function kesalahanSubmit(DokumenPenilaian $dokumen): array
    {
        $d = $this->normalisasi($dokumen, $dokumen->data);
        $errors = [];

        if ($d['tuk'] === null) {
            $errors['isian.tuk'] = 'Pilih jenis TUK.';
        }
        if ($d['tanggal'] === null) {
            $errors['isian.tanggal'] = 'Isi tanggal.';
        }
        if ($this->master($dokumen)['instrumen']['skenario'] === null) {
            $errors['instrumen'] = 'Skenario FR.IA.02 untuk skema ini belum diisi di CMS (Template TPD). Hubungi admin LSP.';
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
        return $this->normalisasi($dokumen, [
            'tuk' => $this->tukAwal($dokumen),
            'tanggal' => $this->info($dokumen)['jadwal']['tanggal'],
        ]);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        return [
            'tuk' => self::tukSah($data['tuk'] ?? null),
            'tanggal' => self::tanggalSah($data['tanggal'] ?? null),
        ];
    }

    /** Data dari skema & template TPD. */
    private function master(DokumenPenilaian $dokumen): array
    {
        $template = $this->template($dokumen);
        $instrumen = $template?->instrumenUntuk(MapaTemplate::IA_02) ?? [];

        return [
            'unit_kompetensi' => $this->unitKompetensi($dokumen),
            'instrumen' => [
                'skenario' => self::teks($instrumen['skenario'] ?? null, 10000),
                'waktu' => self::teks($instrumen['waktu'] ?? null, 100),
            ],
            'penandatangan' => ['penyusun_ia02' => $this->penandatangan($template, 'penyusun_ia02')],
        ];
    }
}
