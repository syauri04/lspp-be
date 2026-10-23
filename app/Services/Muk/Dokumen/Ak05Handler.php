<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use Illuminate\Validation\Rule;

/**
 * FR.AK.05 Laporan Asesmen — diisi & ditandatangani ASESOR (hanya asesor) setelah AK.02 selesai.
 * Rekomendasi K/BK asesi diambil dari keputusan AK.02 (tidak bisa diubah di sini);
 * asesor mengisi keterangan (unit BK), aspek negatif/positif, penolakan hasil, saran, catatan.
 *
 * Isian: { tuk, tanggal, keterangan, aspek, penolakan, saran, catatan }
 */
class Ak05Handler extends PenutupHandler
{
    public const TEKS = ['keterangan', 'aspek', 'penolakan', 'saran', 'catatan'];

    public function kode(): string
    {
        return JenisDokumenMuk::AK_05;
    }

    protected function hanyaAsesor(): bool
    {
        return true;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        $k = $this->keputusan($dokumen);

        return [
            ...$this->payloadDasar($dokumen),
            'rekomendasi' => $k['rekomendasi'],
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:tuk,tanggal,'.implode(',', self::TEKS)],
            'isian.tuk' => ['nullable', Rule::in(array_keys(self::TUK))],
            'isian.tanggal' => ['nullable', 'date_format:Y-m-d'],
            ...collect(self::TEKS)->mapWithKeys(fn ($k) => ["isian.{$k}" => ['nullable', 'string', 'max:5000']])->all(),
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
        if ($this->keputusan($dokumen)['rekomendasi'] === 'belum_kompeten' && $d['keterangan'] === null) {
            $errors['isian.keterangan'] = 'Tuliskan kode & judul unit kompetensi yang dinyatakan BK.';
        }

        return $errors;
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        $k = $this->keputusan($dokumen);

        return $this->normalisasi($dokumen, [
            'tuk' => $k['tuk'],
            'tanggal' => $k['tanggal_selesai'],
            'keterangan' => $k['rekomendasi'] === 'belum_kompeten' ? $k['tindak_lanjut'] : null,
        ]);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        return [
            'tuk' => self::tukSah($data['tuk'] ?? null),
            'tanggal' => self::tanggalSah($data['tanggal'] ?? null),
            ...collect(self::TEKS)->mapWithKeys(fn ($k) => [$k => self::teks($data[$k] ?? null, 5000)])->all(),
        ];
    }
}
