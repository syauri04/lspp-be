<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * FR.IA.06C Lembar Jawaban Pertanyaan Tertulis Esai (semua metode).
 * Terbuka setelah asesi mengirim FR.IA.06A. Asesor menilai pencapaian Ya/Tidak setiap jawaban
 * asesi + umpan balik, menandatangani, lalu asesi menyetujui dengan tanda tangan (pola AK).
 * Kunci jawaban TIDAK ditampilkan di sini (dokumen ini juga dibaca asesi).
 *
 * Isian: {
 *   pencapaian: [ya|tidak|null],
 *   aspek_pengetahuan: tercapai|belum_tercapai|null  (seluruh unit kompetensi yang diujikan),
 *   unit_belum_tercapai: teks Unit/Elemen/KUK (hanya bila aspek = belum_tercapai)
 * }
 */
class Ia06cHandler extends PersetujuanHandler implements PunyaPrasyarat
{
    public const ASPEK = ['tercapai', 'belum_tercapai'];

    public function kode(): string
    {
        return JenisDokumenMuk::IA_06C;
    }

    public function alasanPrasyarat(DokumenPenilaian $dokumen, Collection $semuaDokumen, string $peran): ?string
    {
        $ia06a = $semuaDokumen->first(fn(DokumenPenilaian $d) => $d->jenisDokumen->kode === JenisDokumenMuk::IA_06A);

        return $ia06a?->status === DokumenPenilaian::STATUS_SELESAI ? null : 'Menunggu asesi mengirim jawaban FR.IA.06A.';
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        $master = $dokumen->snapshot ?? $this->master($dokumen);

        return [
            ...$this->payloadDasar($dokumen),
            'header' => $master['header'],
            'jawaban_asesi' => $master['jawaban_asesi'],
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:pencapaian,aspek_pengetahuan,unit_belum_tercapai'],
            'isian.pencapaian' => ['sometimes', 'array', 'max:' . Ia06aHandler::MAKS_SOAL],
            'isian.pencapaian.*' => ['nullable', Rule::in(['ya', 'tidak'])],
            'isian.aspek_pengetahuan' => ['nullable', Rule::in(self::ASPEK)],
            'isian.unit_belum_tercapai' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function kesalahanSubmit(DokumenPenilaian $dokumen): array
    {
        $d = $this->normalisasi($dokumen, $dokumen->data);
        $errors = [];
        foreach ($d['pencapaian'] as $i => $nilai) {
            if ($nilai === null) {
                $errors["isian.pencapaian.{$i}"] = 'Pilih pencapaian Ya atau Tidak.';
            }
        }
        if ($d['aspek_pengetahuan'] === null) {
            $errors['isian.aspek_pengetahuan'] = 'Pilih tercapai atau belum tercapai.';
        } elseif ($d['aspek_pengetahuan'] === 'belum_tercapai' && $d['unit_belum_tercapai'] === null) {
            $errors['isian.unit_belum_tercapai'] = 'Tuliskan Unit/Elemen/KUK yang belum tercapai.';
        }

        return $errors;
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        return [...parent::snapshot($dokumen), ...$this->master($dokumen)];
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        return $this->normalisasi($dokumen, []);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        $jumlah = count(($dokumen->snapshot ?? $this->master($dokumen))['jawaban_asesi']);
        $pencapaian = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $nilai = $data['pencapaian'][$i] ?? null;
            $pencapaian[] = in_array($nilai, ['ya', 'tidak'], true) ? $nilai : null;
        }

        $aspek = in_array($data['aspek_pengetahuan'] ?? null, self::ASPEK, true) ? $data['aspek_pengetahuan'] : null;

        return [
            'pencapaian' => $pencapaian,
            'aspek_pengetahuan' => $aspek,
            // Unit/Elemen/KUK hanya bermakna bila belum tercapai.
            'unit_belum_tercapai' => $aspek === 'belum_tercapai' ? self::teks($data['unit_belum_tercapai'] ?? null, 5000) : null,
        ];
    }

    /** Header & jawaban asesi dari FR.IA.06A (snapshot IA.06A bila ada). Tanpa kunci jawaban. */
    private function master(DokumenPenilaian $dokumen): array
    {
        $ia06a = $this->dokumenLain($dokumen, JenisDokumenMuk::IA_06A);
        $p = Ia06aHandler::persiapanDari($ia06a, []);
        $pertanyaan = $ia06a?->snapshot['pertanyaan'] ?? array_column($p['soal'], 'pertanyaan');
        $jawaban = (array) ($ia06a?->data['jawaban'] ?? []);

        return [
            'header' => ['tuk' => $p['tuk'], 'tanggal' => $p['tanggal'], 'waktu' => $p['waktu']],
            'jawaban_asesi' => array_map(fn($teks, $i) => [
                'pertanyaan' => $teks,
                'jawaban' => self::teks($jawaban[$i] ?? null, 10000),
            ], $pertanyaan, array_keys($pertanyaan)),
        ];
    }
}
