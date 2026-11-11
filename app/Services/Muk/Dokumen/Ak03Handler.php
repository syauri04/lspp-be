<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use Illuminate\Validation\Rule;

/**
 * FR.AK.03 Umpan Balik dan Catatan Asesmen — diisi ASESI setelah keputusan (AK.02 selesai).
 * Asesi menilai 10 komponen (Ya/Tidak + catatan) lalu mengirim (tanpa tanda tangan) -> selesai.
 * Header (TUK, tanggal mulai/selesai) mengikuti AK.02. Asesor dapat membaca setelah dikirim.
 *
 * Isian: { komponen: [{ hasil: ya|tidak|null, catatan }], catatan_lainnya }
 */
class Ak03Handler extends PenutupHandler
{
    public const KOMPONEN = [
        'Saya mendapatkan penjelasan yang cukup memadai mengenai proses asesmen/uji kompetensi',
        'Saya diberikan kesempatan untuk mempelajari standar kompetensi yang akan diujikan dan menilai diri sendiri terhadap pencapaiannya',
        'Asesor memberikan kesempatan untuk mendiskusikan/menegosiasikan metoda, instrumen dan sumber asesmen serta jadwal asesmen',
        'Asesor berusaha menggali seluruh bukti pendukung yang sesuai dengan latar belakang pelatihan dan pengalaman yang saya miliki',
        'Saya sepenuhnya diberikan kesempatan untuk mendemonstrasikan kompetensi yang saya miliki selama asesmen',
        'Saya mendapatkan penjelasan yang memadai mengenai keputusan asesmen',
        'Asesor memberikan umpan balik yang mendukung setelah asesmen serta tindak lanjutnya',
        'Asesor bersama saya mempelajari semua dokumen asesmen serta menandatanganinya',
        // Butir ke-9 (sesuai formulir asli). Data lama digeser oleh migrasi 2026_11_12_100000.
        'Saya mendapatkan jaminan kerahasiaan hasil asesmen serta penjelasan penanganan dokumen asesmen',
        'Asesor menggunakan keterampilan komunikasi yang efektif selama asesmen',
    ];

    public function kode(): string
    {
        return JenisDokumenMuk::AK_03;
    }

    public function pengisi(): string
    {
        return 'asesi';
    }

    public function butuhTandaTangan(): bool
    {
        return false;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        $k = $this->keputusan($dokumen);

        return [
            ...$this->payloadDasar($dokumen),
            'header' => ['tuk' => $k['tuk'], 'tanggal_mulai' => $k['tanggal_mulai'], 'tanggal_selesai' => $k['tanggal_selesai']],
            'komponen' => self::KOMPONEN,
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:komponen,catatan_lainnya'],
            'isian.komponen' => ['sometimes', 'array', 'max:'.count(self::KOMPONEN)],
            'isian.komponen.*' => ['array:hasil,catatan'],
            'isian.komponen.*.hasil' => ['nullable', Rule::in(['ya', 'tidak'])],
            'isian.komponen.*.catatan' => ['nullable', 'string', 'max:2000'],
            'isian.catatan_lainnya' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function kesalahanSubmit(DokumenPenilaian $dokumen): array
    {
        $errors = [];
        foreach ($this->normalisasi($dokumen, $dokumen->data)['komponen'] as $i => $k) {
            if ($k['hasil'] === null) {
                $errors["isian.komponen.{$i}"] = 'Pilih Ya atau Tidak.';
            }
        }

        return $errors;
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        return $this->normalisasi($dokumen, []);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        $komponen = [];
        foreach (array_keys(self::KOMPONEN) as $i) {
            $k = $data['komponen'][$i] ?? [];
            $komponen[] = [
                'hasil' => in_array($k['hasil'] ?? null, ['ya', 'tidak'], true) ? $k['hasil'] : null,
                'catatan' => self::teks($k['catatan'] ?? null),
            ];
        }

        return ['komponen' => $komponen, 'catatan_lainnya' => self::teks($data['catatan_lainnya'] ?? null, 5000)];
    }
}
