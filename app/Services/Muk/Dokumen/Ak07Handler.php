<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use Illuminate\Validation\Rule;

/**
 * FR.AK.07 Ceklis Penyesuaian yang Wajar dan Beralasan.
 * Diisi & ditandatangani asesor, lalu disetujui asesi dengan tanda tangan.
 *
 * Isian (dokumen_penilaians.data):
 * {
 *   tuk: sewaktu|tempat_kerja|mandiri|null, tanggal: Y-m-d|null, potensi: ["1".."5"],
 *   karakter: { k1..k8: { penyesuaian: ya|tidak|null, pilihan: [indeks opsi], lainnya: [{teks, checked}] } },
 *   hasil: { acuan, metode, instrumen }
 * }
 * Isian awal: tanggal dari jadwal, potensi dari FR.MAPA.01, hasil dari standar/metode/instrumen.
 */
class Ak07Handler extends PersetujuanHandler
{
    public const MAKS_LAINNYA = 5;

    /** Karakteristik asesi & pilihan keterangan (urutan = indeks yang disimpan). */
    public const KARAKTER = [
        'k1' => [
            'label' => 'Keterbatasan Asesi terhadap persyaratan bahasa, literasi, numerasi',
            'opsi' => [
                'Memerlukan dukungan pembaca, penerjemah, pelayan, penulis, untuk merekam jawaban Asesi.',
                'Melakukan Asesmen verbal (gunakan pertanyaan lisan/pertanyaan wawancara) dengan dilengkapi gambar diagram dan bentuk-bentuk visual.',
                'Menggunakan hasil produksi.',
                'Menggunakan ceklis observasi/demonstrasi.',
                'Menggunakan daftar instruksi terstruktur.',
            ],
        ],
        'k2' => [
            'label' => 'Penyediaan dukungan pembaca, penerjemah, pelayan, penulis.',
            'opsi' => [
                'Menggunakan pertanyaan lisan dengan dilengkapi gambar diagram dan bentuk-bentuk visual.',
                'Menggunakan pertanyaan wawancara dengan dilengkapi gambar diagram dan bentuk-bentuk visual.',
            ],
        ],
        'k3' => [
            'label' => 'Penggunaan teknologi adaptif atau peralatan khusus. (tidak dapat menggunakan teknologi adaptif (misal: mengoperasikan komputer dan printer, peralatan digital dsb).',
            'opsi' => [
                'Ceklis observasi/demonstrasi.',
                'Pertanyaan lisan.',
                'Pertanyaan tertulis.',
                'Pertanyaan wawancara.',
                'Daftar instruksi terstruktur.',
                'Ceklis verifikasi portofolio.',
                'Menggunakan dukungan operator komputer.',
            ],
        ],
        'k4' => [
            'label' => 'Pelaksanaan asesmen secara fleksibel karena alasan keletihan atau keperluan pengobatan.',
            'opsi' => [
                'Menggunakan juru tulis.',
                'Menggunakan kamera/merekam video/audio.',
                'Memperbolehkan periode waktu yang lebih panjang untuk menyelesaikan tugas pekerjaan dalam asesmen.',
                'Melakukan tugas pekerjaan dalam asesmen dengan waktu lebih pendek.',
                'Menggunakan instruksi-instruksi spesifik pada proyek yang dapat dilakukan pada berbagai tingkatan.',
            ],
        ],
        'k5' => [
            'label' => 'Penyediaan peralatan asesmen berupa braille, audio/video-tape.',
            'opsi' => [
                'Menggunakan pertanyaan lisan.',
                'Menggunakan pertanyaan wawancara.',
            ],
        ],
        'k6' => [
            'label' => 'Penyesuaian tempat fisik/lingkungan asesmen.',
            'opsi' => [
                'Pertanyaan lisan.',
                'Pertanyaan tertulis.',
                'Pertanyaan wawancara.',
                'Ceklis Verifikasi Portofolio.',
                'Ceklis reviu produk.',
                'Daftar instruksi terstruktur.',
            ],
        ],
        'k7' => [
            'label' => 'Pertimbangan umur/usia lanjut/gender asesi. (adanya perbedaan usia dengan asesor yang lebih muda).',
            'opsi' => [
                'Menggunakan studi kasus/daftar instruksi terstruktur.',
                'Menggunakan instrumen asesmen dengan huruf normal jangan terlalu kecil.',
                'Menggunakan asesor dengan jenis kelamin yang sama dengan asesi.',
                'Menggunakan instrumen asesmen yang sama walaupun berbeda jenis kelamin (tidak boleh memberi tanda tambahan pada instrumen asesmen yang digunakan dengan tujuan untuk membedakan jenis kelamin).',
            ],
        ],
        'k8' => [
            'label' => 'Pertimbangan budaya/tradisi/agama.',
            'opsi' => [
                'Menggunakan studi kasus daftar instruksi terstruktur.',
                'Menggunakan asesor tanpa pertimbangan budaya/tradisi/agama.',
                'Menggunakan instrumen asesmen yang sama walaupun berbeda budaya/tradisi/agama.',
            ],
        ],
    ];

    public const HASIL = ['acuan', 'metode', 'instrumen'];

    public function kode(): string
    {
        return JenisDokumenMuk::AK_07;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        return [
            ...$this->payloadDasar($dokumen),
            'opsi_potensi' => self::opsi(Mapa01Handler::ASESI),
            'karakter' => collect(self::KARAKTER)
                ->map(fn ($k, $kunci) => ['kunci' => $kunci, 'label' => $k['label'], 'opsi' => $k['opsi']])
                ->values()->all(),
            'maks_lainnya' => self::MAKS_LAINNYA,
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:tuk,tanggal,potensi,karakter,hasil'],
            'isian.tuk' => ['nullable', Rule::in(array_keys(self::TUK))],
            'isian.tanggal' => ['nullable', 'date_format:Y-m-d'],
            'isian.potensi' => ['sometimes', 'array', 'max:'.count(Mapa01Handler::ASESI)],
            'isian.potensi.*' => ['distinct', Rule::in(array_map('strval', array_keys(Mapa01Handler::ASESI)))],
            'isian.karakter' => ['sometimes', 'array:'.implode(',', array_keys(self::KARAKTER))],
            'isian.karakter.*' => ['array:penyesuaian,pilihan,lainnya'],
            'isian.karakter.*.penyesuaian' => ['nullable', Rule::in(['ya', 'tidak'])],
            // Indeks opsi; rentang per baris dicek di normalisasi(). Tanpa 'distinct' (wildcard bersarang
            // membandingkan lintas baris) — duplikat dibuang di normalisasi().
            'isian.karakter.*.pilihan' => ['sometimes', 'array', 'max:10'],
            'isian.karakter.*.pilihan.*' => ['integer', 'min:0', 'max:9'],
            'isian.karakter.*.lainnya' => ['sometimes', 'array', 'max:'.self::MAKS_LAINNYA],
            'isian.karakter.*.lainnya.*' => ['array:teks,checked'],
            'isian.karakter.*.lainnya.*.teks' => ['nullable', 'string', 'max:255'],
            'isian.karakter.*.lainnya.*.checked' => ['nullable', 'boolean'],
            'isian.hasil' => ['sometimes', 'array:'.implode(',', self::HASIL)],
            'isian.hasil.*' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** karakter & hasil digabung per butir agar PATCH parsial tidak menghapus butir lain. */
    protected function gabung(array $lama, array $isian): array
    {
        $baru = array_replace($lama, array_diff_key($isian, ['karakter' => true, 'hasil' => true]));
        foreach ($isian['karakter'] ?? [] as $kunci => $baris) {
            $baru['karakter'][$kunci] = array_replace($lama['karakter'][$kunci], $baris);
        }
        $baru['hasil'] = array_replace($lama['hasil'], $isian['hasil'] ?? []);

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
        if ($d['potensi'] === []) {
            $errors['isian.potensi'] = 'Pilih minimal satu potensi asesi.';
        }

        foreach ($d['karakter'] as $kunci => $k) {
            if ($k['penyesuaian'] === null) {
                $errors["isian.karakter.{$kunci}"] = 'Pilih Ya atau Tidak.';
            } elseif ($k['penyesuaian'] === 'ya' && $k['pilihan'] === [] && ! collect($k['lainnya'])->contains('checked', true)) {
                $errors["isian.karakter.{$kunci}"] = 'Centang minimal satu keterangan penyesuaian.';
            }
        }

        foreach (['acuan' => 'acuan pembanding', 'metode' => 'metode asesmen', 'instrumen' => 'instrumen asesmen'] as $kunci => $label) {
            if ($d['hasil'][$kunci] === null) {
                $errors["isian.hasil.{$kunci}"] = "Tuliskan {$label}.";
            }
        }

        return $errors;
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        $info = $this->info($dokumen);
        $mapa01 = $this->dokumenLain($dokumen, JenisDokumenMuk::MAPA_01);
        $standar = $mapa01?->snapshot['standar_kompetensi'] ?? [];
        $instrumen = JenisDokumenMuk::query()
            ->untukMetode((string) $info['metode'])
            ->where('jenis_dokumen_muks.kategori', 'IA')
            // Kunci & lembar jawaban bukan instrumen yang disepakati dengan asesi.
            ->whereNotIn('jenis_dokumen_muks.kode', ['FR.IA.06B', 'FR.IA.06C'])
            ->get()
            ->map(fn (JenisDokumenMuk $j) => "{$j->kode} {$j->nama}")
            ->implode(', ');

        return $this->normalisasi($dokumen, [
            'tanggal' => $info['jadwal']['tanggal'],
            'potensi' => $this->potensiDariMapa($dokumen),
            'hasil' => [
                'acuan' => $standar !== [] ? implode('; ', $standar) : ($info['skema']['judul'] ? 'Standar kompetensi skema '.$info['skema']['judul'] : null),
                'metode' => $info['metode_label'],
                'instrumen' => $instrumen ?: null,
            ],
        ]);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        $data ??= [];
        $tanggal = $data['tanggal'] ?? null;

        $hasil = [
            'tuk' => isset(self::TUK[$data['tuk'] ?? '']) ? $data['tuk'] : null,
            'tanggal' => is_string($tanggal) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) ? $tanggal : null,
            'potensi' => array_values(array_intersect(
                array_map('strval', array_keys(Mapa01Handler::ASESI)),
                array_map('strval', (array) ($data['potensi'] ?? [])),
            )),
        ];

        foreach (self::KARAKTER as $kunci => $k) {
            $baris = $data['karakter'][$kunci] ?? [];
            $penyesuaian = in_array($baris['penyesuaian'] ?? null, ['ya', 'tidak'], true) ? $baris['penyesuaian'] : null;
            $ya = $penyesuaian === 'ya';

            $pilihan = array_values(array_unique(array_filter(
                array_map('intval', (array) ($baris['pilihan'] ?? [])),
                fn (int $i) => $i >= 0 && $i < count($k['opsi']),
            )));
            sort($pilihan);

            $lainnya = collect((array) ($baris['lainnya'] ?? []))
                ->map(fn ($l) => ['teks' => self::teks($l['teks'] ?? null, 255), 'checked' => (bool) ($l['checked'] ?? false)])
                ->filter(fn ($l) => $l['teks'] !== null)
                ->take(self::MAKS_LAINNYA)
                // Keterangan hanya berlaku bila penyesuaian "Ya".
                ->map(fn ($l) => [...$l, 'checked' => $ya && $l['checked']])
                ->values()->all();

            $hasil['karakter'][$kunci] = [
                'penyesuaian' => $penyesuaian,
                'pilihan' => $ya ? $pilihan : [],
                'lainnya' => $lainnya,
            ];
        }

        foreach (self::HASIL as $kunci) {
            $hasil['hasil'][$kunci] = self::teks($data['hasil'][$kunci] ?? null);
        }

        return $hasil;
    }
}
