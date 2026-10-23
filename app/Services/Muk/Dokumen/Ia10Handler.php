<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use Illuminate\Validation\Rule;

/**
 * FR.IA.10 Verifikasi Pihak Ketiga (metode VP).
 * Asesor mencatat hasil verifikasi dengan pengawas/penyelia/atasan asesi, menandatangani
 * lalu menyelesaikan (tanpa persetujuan asesi; formulir hanya bertanda tangan asesor).
 * Asesi dapat membaca setelah diselesaikan.
 *
 * Isian: {
 *   tuk, tanggal,
 *   pengawas: { nama, tempat_kerja, alamat, telepon },
 *   jawaban: [ya|tidak|null],                          // sejajar PERTANYAAN
 *   hubungan, lama_bekerja, kedekatan, pengalaman, keyakinan, kebutuhan_pelatihan, komentar
 * }
 * Isian awal: tempat kerja, alamat & telepon dari data pekerjaan FR.APL.01.
 */
class Ia10Handler extends PersetujuanHandler
{
    public const PERTANYAAN = [
        'Apakah asesi bekerja dengan mempertimbangkan Kesehatan, Keamanan dan Keselamatan Kerja?',
        'Apakah asesi berinteraksi dengan harmonis didalam kelompoknya?',
        'Apakah asesi dapat mengelola tugas-tugas secara bersamaan?',
        'Apakah asesi dapat dengan cepat beradaptasi dengan peralatan dan lingkungan yang baru?',
        'Apakah asesi dapat merespon dengan cepat masalah-masalah yang ada di tempat kerjanya?',
        'Apakah Anda bersedia dihubungi jika verifikasi lebih lanjut dari pernyataan ini diperlukan?',
    ];

    public const PENGAWAS = ['nama', 'tempat_kerja', 'alamat', 'telepon'];

    public const URAIAN = ['hubungan', 'lama_bekerja', 'kedekatan', 'pengalaman', 'keyakinan', 'kebutuhan_pelatihan', 'komentar'];

    public function kode(): string
    {
        return JenisDokumenMuk::IA_10;
    }

    public function penyetuju(): ?string
    {
        return null;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        return [
            ...$this->payloadDasar($dokumen),
            'pertanyaan' => self::PERTANYAAN,
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:tuk,tanggal,pengawas,jawaban,'.implode(',', self::URAIAN)],
            'isian.tuk' => ['nullable', Rule::in(array_keys(self::TUK))],
            'isian.tanggal' => ['nullable', 'date_format:Y-m-d'],
            'isian.pengawas' => ['sometimes', 'array:'.implode(',', self::PENGAWAS)],
            'isian.pengawas.*' => ['nullable', 'string', 'max:500'],
            'isian.jawaban' => ['sometimes', 'array', 'max:'.count(self::PERTANYAAN)],
            'isian.jawaban.*' => ['nullable', Rule::in(['ya', 'tidak'])],
            ...collect(self::URAIAN)->mapWithKeys(fn ($k) => ["isian.{$k}" => ['nullable', 'string', 'max:5000']])->all(),
        ];
    }

    protected function gabung(array $lama, array $isian): array
    {
        $baru = array_replace($lama, array_diff_key($isian, ['pengawas' => true]));
        $baru['pengawas'] = array_replace($lama['pengawas'], $isian['pengawas'] ?? []);

        return $baru;
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
        if ($d['pengawas']['nama'] === null) {
            $errors['isian.pengawas'] = 'Tuliskan nama pengawas/penyelia/atasan yang dimintai verifikasi.';
        }
        foreach ($d['jawaban'] as $i => $j) {
            if ($j === null) {
                $errors["isian.jawaban.{$i}"] = 'Pilih Ya atau Tidak.';
            }
        }
        if ($d['keyakinan'] === null) {
            $errors['isian.keyakinan'] = 'Tuliskan keyakinan pihak ketiga atas kinerja asesi.';
        }

        return $errors;
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        $pekerjaan = $this->dokumenLain($dokumen, JenisDokumenMuk::APL_01)?->data['pekerjaan'] ?? [];

        return $this->normalisasi($dokumen, [
            'tuk' => $this->tukAwal($dokumen),
            'tanggal' => $this->info($dokumen)['jadwal']['tanggal'],
            'pengawas' => [
                'tempat_kerja' => $pekerjaan['nama_institusi'] ?? null,
                'alamat' => $pekerjaan['alamat_kantor'] ?? null,
                'telepon' => $pekerjaan['telp'] ?? null,
            ],
        ]);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        $data ??= [];
        $jawaban = [];
        foreach (array_keys(self::PERTANYAAN) as $i) {
            $j = $data['jawaban'][$i] ?? null;
            $jawaban[] = in_array($j, ['ya', 'tidak'], true) ? $j : null;
        }

        return [
            'tuk' => self::tukSah($data['tuk'] ?? null),
            'tanggal' => self::tanggalSah($data['tanggal'] ?? null),
            'pengawas' => collect(self::PENGAWAS)->mapWithKeys(fn ($k) => [$k => self::teks($data['pengawas'][$k] ?? null, 500)])->all(),
            'jawaban' => $jawaban,
            ...collect(self::URAIAN)->mapWithKeys(fn ($k) => [$k => self::teks($data[$k] ?? null, 5000)])->all(),
        ];
    }
}
