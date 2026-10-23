<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Services\Muk\AsesmenLanjutan;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * FR.AK.02 Rekaman Asesmen Kompetensi — keputusan akhir K/BK.
 * Terbuka setelah semua dokumen IA penilaian selesai. Asesor mencentang bukti per unit,
 * memberi rekomendasi Kompeten/Belum Kompeten + tindak lanjut & komentar, menandatangani;
 * asesi menyetujui dengan tanda tangan. Rekomendasi ini menentukan status akhir penilaian.
 *
 * Isian: {
 *   tuk, tanggal_mulai, tanggal_selesai,
 *   bukti: { "u{unitId}": [observasi|portofolio|pihak_ketiga|wawancara|lisan|tertulis|proyek_kerja|lainnya] },
 *   rekomendasi: kompeten|belum_kompeten|null, tindak_lanjut, komentar
 * }
 * Isian awal: bukti per unit dari dokumen IA metode; "indikasi" = hasil belum tercapai di IA
 * (IA.01/IA.03/IA.06C) sebagai bahan pertimbangan asesor.
 */
class Ak02Handler extends PersetujuanHandler implements PunyaPrasyarat
{
    public const BUKTI = [
        'observasi' => 'Observasi Demonstrasi',
        'portofolio' => 'Portofolio',
        'pihak_ketiga' => 'Pernyataan Pihak Ketiga',
        'wawancara' => 'Pertanyaan Wawancara',
        'lisan' => 'Pertanyaan Lisan',
        'tertulis' => 'Pertanyaan Tertulis',
        'proyek_kerja' => 'Proyek Kerja',
        'lainnya' => 'Lainnya',
    ];

    /** Dokumen IA -> jenis bukti yang dihasilkan. */
    private const BUKTI_DARI_IA = [
        'FR.IA.01' => 'observasi',
        'FR.IA.02' => 'observasi',
        'FR.IA.03' => 'lisan',
        'FR.IA.04A' => 'proyek_kerja',
        'FR.IA.04B' => 'proyek_kerja',
        'FR.IA.06A' => 'tertulis',
        'FR.IA.08' => 'portofolio',
        'FR.IA.09' => 'wawancara',
        'FR.IA.10' => 'pihak_ketiga',
    ];

    public const REKOMENDASI = ['kompeten', 'belum_kompeten'];

    public function kode(): string
    {
        return JenisDokumenMuk::AK_02;
    }

    public function alasanPrasyarat(DokumenPenilaian $dokumen, Collection $semuaDokumen, string $peran): ?string
    {
        $belum = $semuaDokumen->filter(fn (DokumenPenilaian $d) => $d->jenisDokumen->kategori === 'IA' && $d->status !== DokumenPenilaian::STATUS_SELESAI);

        return $belum->isEmpty() ? null : 'Selesaikan semua dokumen IA terlebih dahulu ('.$belum->map(fn ($d) => $d->jenisDokumen->kode)->implode(', ').').';
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        return [
            ...$this->payloadDasar($dokumen),
            'unit_kompetensi' => $dokumen->snapshot['unit_kompetensi'] ?? $this->unitKompetensi($dokumen),
            'indikasi' => $dokumen->snapshot['indikasi'] ?? $this->indikasi($dokumen),
            'opsi_bukti' => self::opsi(self::BUKTI),
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:tuk,tanggal_mulai,tanggal_selesai,bukti,rekomendasi,tindak_lanjut,komentar'],
            'isian.tuk' => ['nullable', Rule::in(array_keys(self::TUK))],
            'isian.tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'isian.tanggal_selesai' => ['nullable', 'date_format:Y-m-d'],
            // Kunci unit tak dikenal dibuang di normalisasi(); duplikat dibuang juga.
            'isian.bukti' => ['sometimes', 'array', 'max:200'],
            'isian.bukti.*' => ['array', 'max:'.count(self::BUKTI)],
            'isian.bukti.*.*' => [Rule::in(array_keys(self::BUKTI))],
            'isian.rekomendasi' => ['nullable', Rule::in(self::REKOMENDASI)],
            'isian.tindak_lanjut' => ['nullable', 'string', 'max:5000'],
            'isian.komentar' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function gabung(array $lama, array $isian): array
    {
        $baru = array_replace($lama, array_diff_key($isian, ['bukti' => true]));
        $baru['bukti'] = array_replace($lama['bukti'], $isian['bukti'] ?? []);

        return $baru;
    }

    public function kesalahanSubmit(DokumenPenilaian $dokumen): array
    {
        $d = $this->normalisasi($dokumen, $dokumen->data);
        $errors = [];

        if ($d['tuk'] === null) {
            $errors['isian.tuk'] = 'Pilih jenis TUK.';
        }
        if ($d['tanggal_mulai'] === null || $d['tanggal_selesai'] === null) {
            $errors['isian.tanggal_mulai'] = 'Isi tanggal mulai dan selesai.';
        } elseif ($d['tanggal_selesai'] < $d['tanggal_mulai']) {
            $errors['isian.tanggal_mulai'] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }
        foreach ($d['bukti'] as $kunci => $bukti) {
            if ($bukti === []) {
                $errors["isian.bukti.{$kunci}"] = 'Centang minimal satu bukti untuk unit ini.';
            }
        }

        if ($d['rekomendasi'] === null) {
            $errors['isian.rekomendasi'] = 'Pilih rekomendasi hasil asesmen.';
        } elseif ($d['rekomendasi'] === 'belum_kompeten' && $d['tindak_lanjut'] === null) {
            $errors['isian.tindak_lanjut'] = 'Tuliskan tindak lanjut yang dibutuhkan.';
        } elseif ($d['rekomendasi'] === 'kompeten' && $this->indikasi($dokumen) !== [] && $d['komentar'] === null) {
            // Keputusan K walau ada hasil belum tercapai di IA -> wajib alasan/bukti pendukung.
            $errors['isian.komentar'] = 'Ada hasil belum tercapai pada dokumen IA. Tuliskan alasan/bukti pendukung keputusan Kompeten.';
        }

        return $errors;
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        return [
            ...parent::snapshot($dokumen),
            'unit_kompetensi' => $this->unitKompetensi($dokumen),
            'indikasi' => $this->indikasi($dokumen),
        ];
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        $semua = $dokumen->penilaianMuk->dokumenPenilaian()->with('jenisDokumen')->get();
        // Dokumen asesmen lanjutan (rekomendasi FR.IA.08) hanya menjadi bukti untuk unit dalam lingkupnya.
        $lingkup = AsesmenLanjutan::dariPenilaian($dokumen->penilaianMuk);
        $kodeLanjutan = $lingkup ? AsesmenLanjutan::DOKUMEN[$lingkup['metode']] : [];
        $buktiUnit = fn (int $unitId) => $semua
            ->filter(fn (DokumenPenilaian $d) => ! in_array($d->jenisDokumen->kode, $kodeLanjutan, true) || in_array($unitId, $lingkup['unit'], true))
            ->map(fn (DokumenPenilaian $d) => self::BUKTI_DARI_IA[$d->jenisDokumen->kode] ?? null)
            ->filter()->unique()->values()->all();

        $tanggalJadwal = $this->info($dokumen)['jadwal']['tanggal'];

        return $this->normalisasi($dokumen, [
            'tuk' => $this->tukAwal($dokumen),
            // Asesmen umumnya selesai di hari yang sama; asesor dapat mengubahnya.
            'tanggal_mulai' => $tanggalJadwal,
            'tanggal_selesai' => $tanggalJadwal,
            'bukti' => collect($this->unitKompetensi($dokumen))->mapWithKeys(fn ($u) => ['u'.$u['id'] => $buktiUnit($u['id'])])->all(),
        ]);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        $data ??= [];
        $bukti = [];
        foreach ($this->unitKompetensi($dokumen) as $u) {
            $kunci = 'u'.$u['id'];
            $dipilih = (array) ($data['bukti'][$kunci] ?? []);
            $bukti[$kunci] = array_values(array_filter(array_keys(self::BUKTI), fn ($b) => in_array($b, $dipilih, true)));
        }
        $rekomendasi = in_array($data['rekomendasi'] ?? null, self::REKOMENDASI, true) ? $data['rekomendasi'] : null;

        return [
            'tuk' => self::tukSah($data['tuk'] ?? null),
            'tanggal_mulai' => self::tanggalSah($data['tanggal_mulai'] ?? null),
            'tanggal_selesai' => self::tanggalSah($data['tanggal_selesai'] ?? null),
            'bukti' => $bukti,
            'rekomendasi' => $rekomendasi,
            'tindak_lanjut' => self::teks($data['tindak_lanjut'] ?? null, 5000),
            'komentar' => self::teks($data['komentar'] ?? null, 5000),
        ];
    }

    /**
     * Ringkasan keputusan AK.02 untuk dokumen penutup (AK.03/AK.05/AK.06) & status akhir penilaian.
     *
     * @return array{tuk: ?string, tanggal_mulai: ?string, tanggal_selesai: ?string, rekomendasi: ?string, tindak_lanjut: ?string}
     */
    public static function keputusan(?DokumenPenilaian $ak02): array
    {
        $d = $ak02?->data ?? [];

        return [
            'tuk' => self::tukSah($d['tuk'] ?? null),
            'tanggal_mulai' => self::tanggalSah($d['tanggal_mulai'] ?? null),
            'tanggal_selesai' => self::tanggalSah($d['tanggal_selesai'] ?? null),
            'rekomendasi' => in_array($d['rekomendasi'] ?? null, self::REKOMENDASI, true) ? $d['rekomendasi'] : null,
            'tindak_lanjut' => self::teks($d['tindak_lanjut'] ?? null, 5000),
        ];
    }

    /**
     * Hasil "belum tercapai" pada dokumen IA penilaian ini (bahan pertimbangan keputusan).
     *
     * @return array<int, array{dokumen: string, keterangan: string}>
     */
    private function indikasi(DokumenPenilaian $dokumen): array
    {
        $hasil = [];
        $semua = $dokumen->penilaianMuk->dokumenPenilaian()->with('jenisDokumen')->get()->keyBy(fn ($d) => $d->jenisDokumen->kode);

        if ($ia01 = $semua->get(JenisDokumenMuk::IA_01)) {
            $tidak = collect($ia01->data['elemen'] ?? [])->where('pencapaian', 'tidak')->count();
            if ($tidak > 0) {
                $hasil[] = ['dokumen' => 'FR.IA.01', 'keterangan' => "{$tidak} elemen belum tercapai"];
            }
            if (($ia01->data['rekomendasi'] ?? null) === 'lanjutan') {
                $unit = $ia01->data['lanjutan']['unit'] ?? null;
                $hasil[] = ['dokumen' => 'FR.IA.01', 'keterangan' => 'Direkomendasikan observasi/kegiatan lanjutan'.($unit ? " pada unit: {$unit}" : '')];
            }
        }
        if ($lingkup = AsesmenLanjutan::dariPenilaian($dokumen->penilaianMuk)) {
            $cara = $lingkup['metode'] === 'tpd' ? 'observasi langsung' : 'kegiatan terstruktur';
            $hasil[] = ['dokumen' => 'FR.IA.08', 'keterangan' => 'Portofolio belum memadai; diases lanjut dengan '.$cara.' pada '.count($lingkup['unit']).' unit kompetensi'];
        }
        if ($ia03 = $semua->get(JenisDokumenMuk::IA_03)) {
            $tidak = collect([...($ia03->data['jawaban'] ?? []), ...($ia03->data['tambahan'] ?? [])])->where('pencapaian', 'tidak')->count();
            if ($tidak > 0) {
                $hasil[] = ['dokumen' => 'FR.IA.03', 'keterangan' => "{$tidak} pertanyaan belum tercapai"];
            }
        }
        if ($ia04b = $semua->get(JenisDokumenMuk::IA_04B)) {
            $tidak = collect([...($ia04b->data['jawaban'] ?? []), ...($ia04b->data['tambahan'] ?? [])])->where('pencapaian', 'tidak')->count();
            if ($tidak > 0) {
                $hasil[] = ['dokumen' => 'FR.IA.04B', 'keterangan' => "{$tidak} aspek penilaian belum tercapai"];
            }
            if (($ia04b->data['rekomendasi'] ?? null) === 'belum_kompeten') {
                $hasil[] = ['dokumen' => 'FR.IA.04B', 'keterangan' => 'Direkomendasikan Belum Kompeten'];
            }
        }
        if ($ia06c = $semua->get(JenisDokumenMuk::IA_06C)) {
            $tidak = collect($ia06c->data['pencapaian'] ?? [])->filter(fn ($v) => $v === 'tidak')->count();
            if ($tidak > 0) {
                $hasil[] = ['dokumen' => 'FR.IA.06C', 'keterangan' => "{$tidak} jawaban esai belum tercapai"];
            }
        }

        return $hasil;
    }
}
