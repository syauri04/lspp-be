<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use Illuminate\Validation\Rule;

/**
 * FR.MAPA.02 Peta Instrumen Asesmen (diisi asesor).
 * Asesor mencentang Potensi Asesi (1-5) per instrumen; skema, unit, penyusun & validator
 * dimuat otomatis (template CMS skema + metode).
 */
class Mapa02Handler extends MapaHandler
{
    /** Instrumen asesmen sesuai format FR.MAPA.02 (kunci stabil => [nomor, kode, label]). */
    public const INSTRUMEN = [
        'ia01' => ['1.', 'FR.IA.01. CL', 'Ceklis Observasi Aktivitas Di Tempat Kerja atau Tempat Kerja Simulasi'],
        'ia02' => ['2.', 'FR.IA.02. TPD', 'Tugas Praktik Demonstrasi'],
        'ia03' => ['3.', 'FR.IA.03. PMO', 'Pertanyaan Untuk Mendukung Observasi'],
        'ia04a' => ['4.a', 'FR.IA.04A. DIT', 'Daftar Instruksi Terstruktur (Penjelasan Singkat Proyek Terkait Pekerjaan/Kegiatan Terstruktur Lainnya)'],
        'ia04b' => ['4.b', 'FR.IA.04B. DIT', 'Penilaian Proyek Singkat atau Kegiatan Terstruktur Lainnya'],
        'ia05' => ['5.', 'FR.IA.05. DPT', 'Daftar Pertanyaan Tertulis Pilihan Ganda'],
        'ia06' => ['6.', 'FR.IA.06. DPT', 'Daftar Pertanyaan Tertulis Pilihan Esai'],
        'ia07' => ['7.', 'FR.IA.07. DPL', 'Daftar Pertanyaan Lisan'],
        'ia08' => ['8.', 'FR.IA.08. CVP', 'Ceklis Verifikasi Portofolio'],
        'ia09' => ['9.', 'FR.IA.09. PW', 'Pertanyaan Wawancara'],
        'ia10' => ['10.', 'FR.IA.10. VPK', 'Verifikasi Pihak Ketiga'],
        'ia11' => ['11.', 'FR.IA.11. CRP', 'Ceklis Reviu Produk'],
    ];

    public const POTENSI = [1, 2, 3, 4, 5];

    public function kode(): string
    {
        return JenisDokumenMuk::MAPA_02;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        $master = $dokumen->snapshot ?? $this->master($dokumen);

        return [
            'isian' => $this->normalisasi($dokumen->data),
            'skema' => $master['skema'],
            'unit_kompetensi' => $master['unit_kompetensi'],
            'metode' => $dokumen->penilaianMuk->metode_asesmen,
            'instrumen' => collect(self::INSTRUMEN)->map(fn ($v, $k) => ['kunci' => $k, 'nomor' => $v[0], 'kode' => $v[1], 'label' => $v[2]])->values()->all(),
            'penyusun' => self::tanpaPath($master['penandatangan']['penyusun_mapa02']),
            'keterangan_potensi' => array_values(Mapa01Handler::ASESI),
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:potensi'],
            'isian.potensi' => ['present', 'array:'.implode(',', array_keys(self::INSTRUMEN))],
            'isian.potensi.*' => ['array', 'max:5'],
            'isian.potensi.*.*' => ['integer', Rule::in(self::POTENSI)], // duplikat dibuang di normalisasi()
        ];
    }

    public function simpan(DokumenPenilaian $dokumen, array $validated, array &$hapusNanti): bool
    {
        $baru = $this->normalisasi($validated['isian']);
        if ($baru === $this->normalisasi($dokumen->data)) {
            return false;
        }
        $dokumen->data = $baru;

        return true;
    }

    public function kesalahanSubmit(DokumenPenilaian $dokumen): array
    {
        $isian = $this->normalisasi($dokumen->data);

        return collect($isian['potensi'])->flatten()->isEmpty()
            ? ['isian.potensi' => 'Centang potensi asesi pada minimal satu instrumen asesmen.']
            : [];
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        $master = $this->master($dokumen);
        $master['penandatangan'] = array_map(fn ($baris) => $this->salinTtd($dokumen, $baris), $master['penandatangan']);

        return $master;
    }

    private function master(DokumenPenilaian $dokumen): array
    {
        $skema = $this->skema($dokumen);

        return [
            'skema' => $this->infoSkema($skema),
            'unit_kompetensi' => $this->daftarUnit($skema),
            'penandatangan' => [
                'penyusun_mapa02' => $this->penandatangan($this->template($dokumen), 'penyusun_mapa02'),
            ],
        ];
    }

    /** { potensi: { ia01: [1,3], ... } } untuk semua instrumen, nilai terurut & unik. */
    private function normalisasi(?array $data): array
    {
        $potensi = [];
        foreach (array_keys(self::INSTRUMEN) as $k) {
            $nilai = array_map('intval', (array) ($data['potensi'][$k] ?? []));
            $nilai = array_values(array_unique(array_intersect($nilai, self::POTENSI)));
            sort($nilai);
            $potensi[$k] = $nilai;
        }

        return ['potensi' => $potensi];
    }
}
