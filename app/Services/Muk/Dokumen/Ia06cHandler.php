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
 * Isian: { pencapaian: [ya|tidak|null], umpan_balik }
 */
class Ia06cHandler extends PersetujuanHandler implements PunyaPrasyarat
{
    public const UMPAN_BALIK_AWAL = "Aspek pengetahuan seluruh unit kompetensi yang diujikan (tercapai/ belum tercapai)*\n\nTuliskan Unit/Elemen/KUK jika belum tercapai: ....";

    public function kode(): string
    {
        return JenisDokumenMuk::IA_06C;
    }

    public function alasanPrasyarat(DokumenPenilaian $dokumen, Collection $semuaDokumen, string $peran): ?string
    {
        $ia06a = $semuaDokumen->first(fn (DokumenPenilaian $d) => $d->jenisDokumen->kode === JenisDokumenMuk::IA_06A);

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
            'isian' => ['required', 'array:pencapaian,umpan_balik'],
            'isian.pencapaian' => ['sometimes', 'array', 'max:'.Ia06aHandler::MAKS_SOAL],
            'isian.pencapaian.*' => ['nullable', Rule::in(['ya', 'tidak'])],
            'isian.umpan_balik' => ['nullable', 'string', 'max:5000'],
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
        if ($d['umpan_balik'] === null) {
            $errors['isian.umpan_balik'] = 'Tuliskan umpan balik untuk asesi.';
        }

        return $errors;
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        return [...parent::snapshot($dokumen), ...$this->master($dokumen)];
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        return $this->normalisasi($dokumen, ['umpan_balik' => self::UMPAN_BALIK_AWAL]);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        $jumlah = count(($dokumen->snapshot ?? $this->master($dokumen))['jawaban_asesi']);
        $pencapaian = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $nilai = $data['pencapaian'][$i] ?? null;
            $pencapaian[] = in_array($nilai, ['ya', 'tidak'], true) ? $nilai : null;
        }

        return ['pencapaian' => $pencapaian, 'umpan_balik' => self::teks($data['umpan_balik'] ?? null, 5000)];
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
            'jawaban_asesi' => array_map(fn ($teks, $i) => [
                'pertanyaan' => $teks,
                'jawaban' => self::teks($jawaban[$i] ?? null, 10000),
            ], $pertanyaan, array_keys($pertanyaan)),
        ];
    }
}
