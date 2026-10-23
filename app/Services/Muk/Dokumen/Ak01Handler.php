<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use Illuminate\Validation\Rule;

/**
 * FR.AK.01 Persetujuan Asesmen dan Kerahasiaan.
 * Asesor mengisi (TUK, bukti yang akan dikumpulkan, pelaksanaan) & menandatangani,
 * lalu asesi menyetujui pernyataan dengan tanda tangan.
 *
 * Isian (dokumen_penilaians.data):
 * { tuk, bukti: [kunci], bukti_lainnya, pelaksanaan: { tanggal: Y-m-d, waktu, tuk } }
 * Isian awal: TUK dari FR.AK.07, bukti dari rencana asesmen FR.MAPA.01, pelaksanaan dari jadwal.
 */
class Ak01Handler extends PersetujuanHandler
{
    /** Urutan sesuai formulir (2 kolom, mengalir per kolom). */
    public const BUKTI = [
        'portofolio' => 'Hasil Verifikasi Portofolio',
        'observasi' => 'Hasil Observasi Langsung',
        'lisan' => 'Hasil Pertanyaan Lisan',
        'lainnya' => 'Lainnya',
        'reviu_produk' => 'Hasil Reviu Produk',
        'kegiatan_terstruktur' => 'Hasil Kegiatan Terstruktur',
        'tertulis' => 'Hasil Pertanyaan Tertulis',
        'wawancara' => 'Hasil Pertanyaan Wawancara',
    ];

    public const PERNYATAAN = [
        'asesi_banding' => 'Bahwa saya telah mendapatkan penjelasan terkait hak dan prosedur banding asesmen dari asesor.',
        'asesor' => 'Menyatakan tidak akan membuka hasil pekerjaan yang saya peroleh karena penugasan saya sebagai Asesor dalam pekerjaan Asesmen kepada siapapun atau organisasi apapun selain kepada pihak yang berwenang sehubungan dengan kewajiban saya sebagai Asesor yang ditugaskan oleh LSP.',
        'asesi_setuju' => 'Saya setuju mengikuti asesmen dengan pemahaman bahwa informasi yang dikumpulkan hanya digunakan untuk pengembangan profesional dan hanya dapat diakses oleh orang tertentu saja.',
    ];

    /** Kolom rencana asesmen FR.MAPA.01 -> bukti yang akan dikumpulkan. */
    private const BUKTI_DARI_KOLOM = [
        'verifikasi_portofolio' => 'portofolio',
        'observasi_langsung' => 'observasi',
        'kegiatan_terstruktur' => 'kegiatan_terstruktur',
        'reviu_produk' => 'reviu_produk',
    ];

    /** Kode perangkat pada rencana asesmen -> bukti. */
    private const BUKTI_DARI_PERANGKAT = ['DPL' => 'lisan', 'DPT' => 'tertulis', 'PW' => 'wawancara'];

    public function kode(): string
    {
        return JenisDokumenMuk::AK_01;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        return [
            ...$this->payloadDasar($dokumen),
            'opsi_bukti' => self::opsi(self::BUKTI),
            'pernyataan' => self::PERNYATAAN,
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:tuk,bukti,bukti_lainnya,pelaksanaan'],
            'isian.tuk' => ['nullable', Rule::in(array_keys(self::TUK))],
            'isian.bukti' => ['sometimes', 'array', 'max:'.count(self::BUKTI)],
            'isian.bukti.*' => ['distinct', Rule::in(array_keys(self::BUKTI))],
            'isian.bukti_lainnya' => ['nullable', 'string', 'max:255'],
            'isian.pelaksanaan' => ['sometimes', 'array:tanggal,waktu,tuk'],
            'isian.pelaksanaan.tanggal' => ['nullable', 'date_format:Y-m-d'],
            'isian.pelaksanaan.waktu' => ['nullable', 'string', 'max:100'],
            'isian.pelaksanaan.tuk' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function gabung(array $lama, array $isian): array
    {
        $baru = array_replace($lama, array_diff_key($isian, ['pelaksanaan' => true]));
        $baru['pelaksanaan'] = array_replace($lama['pelaksanaan'], $isian['pelaksanaan'] ?? []);

        return $baru;
    }

    public function kesalahanSubmit(DokumenPenilaian $dokumen): array
    {
        $d = $this->normalisasi($dokumen, $dokumen->data);
        $errors = [];

        if ($d['tuk'] === null) {
            $errors['isian.tuk'] = 'Pilih jenis TUK.';
        }
        if ($d['bukti'] === []) {
            $errors['isian.bukti'] = 'Pilih minimal satu bukti yang akan dikumpulkan.';
        } elseif (in_array('lainnya', $d['bukti'], true) && $d['bukti_lainnya'] === null) {
            $errors['isian.bukti'] = 'Tuliskan bukti lainnya.';
        }
        foreach (['tanggal' => 'Isi hari/tanggal pelaksanaan.', 'waktu' => 'Isi waktu pelaksanaan.', 'tuk' => 'Isi TUK pelaksanaan.'] as $kunci => $pesan) {
            if ($d['pelaksanaan'][$kunci] === null) {
                $errors["isian.pelaksanaan.{$kunci}"] = $pesan;
            }
        }

        return $errors;
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        $info = $this->info($dokumen);
        $jadwal = $info['jadwal'];
        [$bukti, $lainnya] = $this->buktiDariMapa($dokumen);

        $waktu = $jadwal['waktu_mulai']
            ? $jadwal['waktu_mulai'].($jadwal['waktu_selesai'] ? ' - '.$jadwal['waktu_selesai'] : '').' WIB'
            : null;
        $tuk = $jadwal['tuk'] ? trim($jadwal['tuk']['nama'].($jadwal['tuk']['kota'] ? ', '.$jadwal['tuk']['kota'] : '')) : null;

        return $this->normalisasi($dokumen, [
            'tuk' => $this->dokumenLain($dokumen, JenisDokumenMuk::AK_07)?->data['tuk'] ?? null,
            'bukti' => $bukti,
            'bukti_lainnya' => $lainnya,
            'pelaksanaan' => ['tanggal' => $jadwal['tanggal'], 'waktu' => $waktu, 'tuk' => $tuk],
        ]);
    }

    /**
     * Bukti dari rencana asesmen FR.MAPA.01 (snapshot): kolom metode yang terisi & kode
     * perangkat (DPL/DPT/PW). Verifikasi pihak ketiga -> "Lainnya".
     *
     * @return array{0: string[], 1: ?string}
     */
    private function buktiDariMapa(DokumenPenilaian $dokumen): array
    {
        $rencana = $this->dokumenLain($dokumen, JenisDokumenMuk::MAPA_01)?->snapshot['rencana'] ?? [];
        $bukti = [];
        $lainnya = null;

        foreach ($rencana as $baris) {
            foreach (self::BUKTI_DARI_KOLOM as $kolom => $kunci) {
                if (filled($baris[$kolom] ?? null)) {
                    $bukti[] = $kunci;
                }
            }
            if (filled($baris['verifikasi_pihak_ketiga'] ?? null)) {
                $bukti[] = 'lainnya';
                $lainnya = 'Hasil Verifikasi Pihak Ketiga';
            }

            $teks = implode(' ', array_map(fn ($k) => (string) ($baris[$k] ?? ''), ['observasi_langsung', 'kegiatan_terstruktur', 'tanya_jawab', 'verifikasi_portofolio', 'reviu_produk', 'verifikasi_pihak_ketiga']));
            foreach (self::BUKTI_DARI_PERANGKAT as $kode => $kunci) {
                if (preg_match('/\b'.$kode.'\b/', $teks)) {
                    $bukti[] = $kunci;
                }
            }
        }

        return [array_values(array_unique($bukti)), $lainnya];
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        $data ??= [];
        $tanggal = $data['pelaksanaan']['tanggal'] ?? null;
        $bukti = array_values(array_intersect(array_keys(self::BUKTI), (array) ($data['bukti'] ?? [])));

        return [
            'tuk' => isset(self::TUK[$data['tuk'] ?? '']) ? $data['tuk'] : null,
            'bukti' => $bukti,
            // Teks "Lainnya" hanya berarti bila "Lainnya" dicentang.
            'bukti_lainnya' => in_array('lainnya', $bukti, true) ? self::teks($data['bukti_lainnya'] ?? null, 255) : null,
            'pelaksanaan' => [
                'tanggal' => is_string($tanggal) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) ? $tanggal : null,
                'waktu' => self::teks($data['pelaksanaan']['waktu'] ?? null, 100),
                'tuk' => self::teks($data['pelaksanaan']['tuk'] ?? null, 255),
            ],
        ];
    }
}
