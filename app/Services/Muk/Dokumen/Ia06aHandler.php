<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\MapaTemplate;
use Illuminate\Validation\Rule;

/**
 * FR.IA.06A Pertanyaan Tertulis Esai (semua metode).
 * 1. Asesor menyiapkan TUK, tanggal & waktu lalu membuka untuk asesi; soal (pertanyaan + kunci)
 *    dari template Umum di CMS dibekukan saat dibuka.
 * 2. Asesi menuliskan jawaban setiap pertanyaan lalu mengirim (tanpa tanda tangan) -> selesai.
 * Penilaian jawaban dilakukan asesor di FR.IA.06C; kunci jawaban hanya di FR.IA.06B.
 *
 * KEAMANAN: kunci jawaban disimpan di data_review.persiapan.soal dan TIDAK PERNAH dimasukkan
 * ke payload dokumen ini (asesi hanya menerima teks pertanyaan).
 *
 * data (asesi)        : { jawaban: [teks] }
 * data_review (asesor): { persiapan: { tuk, tanggal, waktu, soal: [{pertanyaan, kunci}], dibuka_at } }
 */
class Ia06aHandler extends PersetujuanHandler implements DisiapkanPeninjau
{
    use MemakaiTemplatePerangkat;

    public const MAKS_SOAL = 50;

    public function kode(): string
    {
        return JenisDokumenMuk::IA_06A;
    }

    public function pengisi(): string
    {
        return 'asesi';
    }

    public function peninjau(): ?string
    {
        return null;
    }

    public function penyetuju(): ?string
    {
        return null;
    }

    public function penyiap(): string
    {
        return 'asesor';
    }

    public function butuhTandaTangan(): bool
    {
        return false;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        $p = $this->persiapanLengkap($dokumen);
        $soal = self::persiapanDari($dokumen, $this->soalTemplate($dokumen))['soal'];

        return [
            'isian' => $this->normalisasi($dokumen, $dokumen->data),
            'info' => $dokumen->snapshot['info'] ?? $this->info($dokumen),
            'opsi_tuk' => self::opsi(self::TUK),
            'persiapan' => [
                'tuk' => $p['tuk'],
                'tanggal' => $p['tanggal'],
                'waktu' => $p['waktu'],
                'dibuka' => $p['dibuka_at'] !== null,
            ],
            // Hanya teks pertanyaan — kunci jawaban tidak dikirim.
            'pertanyaan' => array_column($soal, 'pertanyaan'),
        ];
    }

    /* ----------------------------- jawaban asesi ----------------------------- */

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:jawaban'],
            'isian.jawaban' => ['present', 'array', 'max:'.self::MAKS_SOAL],
            'isian.jawaban.*' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function kesalahanSubmit(DokumenPenilaian $dokumen): array
    {
        $errors = [];
        foreach ($this->normalisasi($dokumen, $dokumen->data)['jawaban'] as $i => $jawaban) {
            if ($jawaban === null) {
                $errors["isian.jawaban.{$i}"] = 'Tuliskan jawaban Anda.';
            }
        }

        return $errors;
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        $p = self::persiapanDari($dokumen, $this->soalTemplate($dokumen));

        return [...parent::snapshot($dokumen), 'pertanyaan' => array_column($p['soal'], 'pertanyaan')];
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        return $this->normalisasi($dokumen, []);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        $jumlah = count(self::persiapanDari($dokumen, $this->soalTemplate($dokumen))['soal']);
        $jawaban = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $jawaban[] = self::teks($data['jawaban'][$i] ?? null, 10000);
        }

        return ['jawaban' => $jawaban];
    }

    /* ----------------------------- persiapan asesor ----------------------------- */

    public function sudahDibuka(DokumenPenilaian $dokumen): bool
    {
        return ($dokumen->data_review['persiapan']['dibuka_at'] ?? null) !== null;
    }

    public function rulesPersiapan(DokumenPenilaian $dokumen): array
    {
        return [
            'persiapan' => ['required', 'array:tuk,tanggal,waktu'],
            'persiapan.tuk' => ['nullable', Rule::in(array_keys(self::TUK))],
            'persiapan.tanggal' => ['nullable', 'date_format:Y-m-d'],
            'persiapan.waktu' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function simpanPersiapan(DokumenPenilaian $dokumen, array $validated): bool
    {
        $lama = $this->persiapanLengkap($dokumen);
        $masuk = array_replace($lama, $validated['persiapan']);
        $baru = [
            'tuk' => self::tukSah($masuk['tuk']),
            'tanggal' => self::tanggalSah($masuk['tanggal']),
            'waktu' => self::teks($masuk['waktu'], 100),
            'soal' => [],
            'dibuka_at' => null,
        ];
        if ($baru === $lama && isset($dokumen->data_review['persiapan'])) {
            return false;
        }
        $dokumen->data_review = [...($dokumen->data_review ?? []), 'persiapan' => $baru];

        return true;
    }

    public function kesalahanBuka(DokumenPenilaian $dokumen): array
    {
        $p = $this->persiapanLengkap($dokumen);
        $errors = [];
        if ($p['tuk'] === null) {
            $errors['persiapan.tuk'] = 'Pilih jenis TUK.';
        }
        if ($p['tanggal'] === null) {
            $errors['persiapan.tanggal'] = 'Isi tanggal.';
        }
        if ($this->soalTemplate($dokumen) === []) {
            $errors['persiapan.soal'] = 'Soal esai FR.IA.06 untuk skema ini belum diisi di CMS (Template Umum). Hubungi admin LSP.';
        }

        return $errors;
    }

    public function buka(DokumenPenilaian $dokumen): void
    {
        $p = $this->persiapanLengkap($dokumen);
        $p['soal'] = $this->soalTemplate($dokumen);
        $p['dibuka_at'] = now()->toIso8601String();
        $dokumen->data_review = [...($dokumen->data_review ?? []), 'persiapan' => $p];
    }

    /* ----------------------------- bantuan (dipakai juga IA.06B/C) ----------------------------- */

    /**
     * Persiapan IA.06A: tersimpan (soal dibekukan bila sudah dibuka) atau soal template terkini.
     *
     * @param  array<int, array{pertanyaan: string, kunci: ?string}>  $soalTemplate
     * @return array{tuk: ?string, tanggal: ?string, waktu: ?string, soal: array, dibuka_at: ?string}
     */
    public static function persiapanDari(?DokumenPenilaian $ia06a, array $soalTemplate): array
    {
        $p = $ia06a?->data_review['persiapan'] ?? [];
        $dibuka = $p['dibuka_at'] ?? null;

        return [
            'tuk' => self::tukSah($p['tuk'] ?? null),
            'tanggal' => self::tanggalSah($p['tanggal'] ?? null),
            'waktu' => self::teks($p['waktu'] ?? null, 100),
            'soal' => $dibuka !== null ? array_values((array) ($p['soal'] ?? [])) : $soalTemplate,
            'dibuka_at' => $dibuka,
        ];
    }

    /**
     * Soal (pertanyaan + kunci) dari template Umum skema.
     *
     * @return array<int, array{pertanyaan: string, kunci: ?string}>
     */
    public function soalTemplate(DokumenPenilaian $dokumen): array
    {
        $soal = (array) ($this->templateUmum($dokumen)?->instrumenUntuk(MapaTemplate::IA_06)['soal'] ?? []);

        return array_slice(array_values(array_filter(array_map(fn ($s) => [
            'pertanyaan' => self::teks($s['pertanyaan'] ?? null, 5000),
            'kunci' => self::teks($s['kunci'] ?? null, 10000),
        ], $soal), fn ($s) => $s['pertanyaan'] !== null)), 0, self::MAKS_SOAL);
    }

    /** Waktu default dari template Umum. */
    public function waktuTemplate(DokumenPenilaian $dokumen): ?string
    {
        return self::teks($this->templateUmum($dokumen)?->instrumenUntuk(MapaTemplate::IA_06)['waktu'] ?? null, 100);
    }

    /** Persiapan dengan default TUK (AK), tanggal (jadwal) & waktu (template) bila belum disimpan. */
    private function persiapanLengkap(DokumenPenilaian $dokumen): array
    {
        $tersimpan = isset($dokumen->data_review['persiapan']);
        $p = self::persiapanDari($dokumen, []);

        return [
            'tuk' => $tersimpan ? $p['tuk'] : $this->tukAwal($dokumen),
            'tanggal' => $tersimpan ? $p['tanggal'] : $this->info($dokumen)['jadwal']['tanggal'],
            'waktu' => $tersimpan ? $p['waktu'] : $this->waktuTemplate($dokumen),
            'soal' => $p['dibuka_at'] !== null ? $p['soal'] : [],
            'dibuka_at' => $p['dibuka_at'],
        ];
    }
}
