<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * FR.AK.04 Banding Asesmen — diisi ASESI, terbuka setelah FR.MAPA.01 & FR.MAPA.02 terisi (seperti AK.07),
 * lalu dikirim -> selesai (tanpa peninjau & tanpa penyetuju). Asesor dapat membaca setelah dikirim.
 *
 * Isian: {
 *   jawaban: [{ hasil: ya|tidak|null }] (urut sesuai PERTANYAAN),
 *   alasan_banding
 * }
 *
 */
class Ak04Handler extends PersetujuanHandler implements PunyaPrasyarat
{
    /** Dokumen yang harus sudah diisi asesor sebelum AK.04 terbuka. */
    private const PRASYARAT = [JenisDokumenMuk::MAPA_01, JenisDokumenMuk::MAPA_02];

    /** Status MAPA yang dianggap "sudah diisi". SESUAIKAN dengan aturan Ak07Handler. */
    private const STATUS_TERISI = [DokumenPenilaian::STATUS_SELESAI];

    public const PERTANYAAN = [
        'Apakah Proses Banding telah dijelaskan kepada anda?',
        'Apakah Anda telah mendiskusikan Banding dengan Asesor?',
        'Apakah Anda mau melibatkan "orang lain" membantu Anda dalam Proses Banding?',
    ];

    public function kode(): string
    {
        return JenisDokumenMuk::AK_04;
    }

    public function pengisi(): string
    {
        return 'asesi';
    }

    public function penyetuju(): ?string
    {
        return null;
    }

    public function alasanPrasyarat(DokumenPenilaian $dokumen, Collection $semuaDokumen, string $peran): ?string
    {
        foreach (self::PRASYARAT as $kode) {
            $d = $semuaDokumen->first(fn(DokumenPenilaian $x) => $x->jenisDokumen->kode === $kode);
            if (! $d || ! in_array($d->status, self::STATUS_TERISI, true)) {
                return "Menunggu {$kode} diisi asesor.";
            }
        }

        return null;
    }

    public function butuhTandaTangan(): bool
    {
        return true;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        return [
            ...$this->payloadDasar($dokumen),
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:jawaban,alasan_banding'],
            'isian.jawaban' => ['sometimes', 'array', 'max:' . count(self::PERTANYAAN)],
            'isian.jawaban.*' => ['array:hasil'],
            'isian.jawaban.*.hasil' => ['nullable', Rule::in(['ya', 'tidak'])],
            'isian.alasan_banding' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function kesalahanSubmit(DokumenPenilaian $dokumen): array
    {
        $data = $this->normalisasi($dokumen, $dokumen->data);
        $errors = [];

        foreach ($data['jawaban'] as $i => $j) {
            if ($j['hasil'] === null) {
                $errors["isian.jawaban.{$i}"] = 'Pilih Ya atau Tidak.';
            }
        }
        if ($data['alasan_banding'] === null || $data['alasan_banding'] === '') {
            $errors['isian.alasan_banding'] = 'Alasan banding wajib diisi.';
        }

        return $errors;
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        return $this->normalisasi($dokumen, []);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        $jawaban = [];
        foreach (array_keys(self::PERTANYAAN) as $i) {
            $hasil = $data['jawaban'][$i]['hasil'] ?? null;
            $jawaban[] = ['hasil' => in_array($hasil, ['ya', 'tidak'], true) ? $hasil : null];
        }

        return [
            'jawaban' => $jawaban,
            'alasan_banding' => self::teks($data['alasan_banding'] ?? null, 5000),
        ];
    }
}
