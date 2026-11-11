<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\MapaTemplate;
use App\Services\Muk\TandaTanganService;
use Illuminate\Validation\Rule;

/**
 * FR.IA.04B Penilaian Proyek Singkat atau Kegiatan Terstruktur Lainnya, metode KT.
 * Aspek penilaian (lingkup, pertanyaan, kesesuaian) dari template KT (CMS), disalin ke isian saat
 * pertama disimpan; asesor dapat menambah pertanyaan. Asesor mencatat tanggapan asesi, pencapaian
 * Ya/Tidak & rekomendasi K/BK, menandatangani; asesi menyetujui. Penyusun/validator dibekukan.
 *
 * Isian tersimpan: {
 *   tuk, tanggal, judul_kegiatan,
 *   aspek: [{ lingkup, pertanyaan, kesesuaian }],         // server-managed (template)
 *   jawaban: [{ tanggapan, pencapaian, kesesuaian }],     // sejajar aspek; kesesuaian awal dari template
 *   tambahan: [{ lingkup, pertanyaan, kesesuaian, tanggapan, pencapaian }],
 *   rekomendasi_teks, rekomendasi: kompeten|belum_kompeten
 * }
 */
class Ia04bHandler extends PersetujuanHandler implements PunyaPenandatangan
{
    use MemakaiTemplatePerangkat;

    public const MAKS_TAMBAHAN = 10;

    public const REKOMENDASI = ['kompeten', 'belum_kompeten'];

    public function __construct(protected TandaTanganService $ttd) {}

    public function kode(): string
    {
        return JenisDokumenMuk::IA_04B;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        $penyusun = $dokumen->snapshot['penandatangan']['penyusun_ia04b'] ?? $this->penandatangan($this->template($dokumen), 'penyusun_ia04b');

        return [
            ...$this->payloadDasar($dokumen),
            'unit_kompetensi' => $dokumen->snapshot['unit_kompetensi'] ?? $this->unitKompetensi($dokumen),
            'penyusun' => self::tanpaPath($penyusun),
            'maks_tambahan' => self::MAKS_TAMBAHAN,
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        $pencapaian = ['nullable', Rule::in(['ya', 'tidak'])];

        return [
            'isian' => ['required', 'array:tuk,tanggal,judul_kegiatan,jawaban,tambahan,rekomendasi_teks,rekomendasi'],
            'isian.tuk' => ['nullable', Rule::in(array_keys(self::TUK))],
            'isian.tanggal' => ['nullable', 'date_format:Y-m-d'],
            'isian.judul_kegiatan' => ['nullable', 'string', 'max:500'],
            'isian.jawaban' => ['sometimes', 'array', 'max:30'],
            'isian.jawaban.*' => ['array:tanggapan,pencapaian,kesesuaian'],
            'isian.jawaban.*.kesesuaian' => ['nullable', 'string', 'max:500'],
            'isian.jawaban.*.tanggapan' => ['nullable', 'string', 'max:5000'],
            'isian.jawaban.*.pencapaian' => $pencapaian,
            'isian.tambahan' => ['sometimes', 'array', 'max:'.self::MAKS_TAMBAHAN],
            'isian.tambahan.*' => ['array:lingkup,pertanyaan,kesesuaian,tanggapan,pencapaian'],
            'isian.tambahan.*.lingkup' => ['nullable', 'string', 'max:500'],
            'isian.tambahan.*.pertanyaan' => ['nullable', 'string', 'max:5000'],
            'isian.tambahan.*.kesesuaian' => ['nullable', 'string', 'max:500'],
            'isian.tambahan.*.tanggapan' => ['nullable', 'string', 'max:5000'],
            'isian.tambahan.*.pencapaian' => $pencapaian,
            'isian.rekomendasi_teks' => ['nullable', 'string', 'max:5000'],
            'isian.rekomendasi' => ['nullable', Rule::in(self::REKOMENDASI)],
        ];
    }

    /** Aspek template selalu dari isian lama (tidak bisa ditimpa klien). */
    protected function gabung(array $lama, array $isian): array
    {
        return [...array_replace($lama, $isian), 'aspek' => $lama['aspek']];
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
        if ($d['judul_kegiatan'] === null) {
            $errors['isian.judul_kegiatan'] = 'Isi judul kegiatan terstruktur.';
        }
        if ($d['aspek'] === [] && $d['tambahan'] === []) {
            $errors['isian.jawaban'] = 'Belum ada aspek penilaian. Tambahkan pertanyaan atau minta admin mengisi Template KT di CMS.';
        }
        $adaTidak = false;
        foreach (['jawaban' => $d['jawaban'], 'tambahan' => $d['tambahan']] as $grup => $baris) {
            foreach ($baris as $i => $j) {
                if ($j['pencapaian'] === null) {
                    $errors["isian.{$grup}.{$i}"] = 'Pilih pencapaian Ya atau Tidak.';
                }
                $adaTidak = $adaTidak || $j['pencapaian'] === 'tidak';
            }
        }
        if ($d['rekomendasi'] === null) {
            $errors['isian.rekomendasi'] = 'Pilih rekomendasi asesor.';
        } elseif ($d['rekomendasi'] === 'kompeten' && $adaTidak) {
            $errors['isian.rekomendasi'] = 'Ada aspek yang belum tercapai; asesi belum dapat direkomendasikan kompeten.';
        }

        return $errors;
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        $baris = $this->penandatangan($this->template($dokumen), 'penyusun_ia04b');

        return [
            ...parent::snapshot($dokumen),
            'unit_kompetensi' => $this->unitKompetensi($dokumen),
            'penandatangan' => ['penyusun_ia04b' => $this->salinTtd($dokumen, $baris)],
        ];
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        return $this->normalisasi($dokumen, [
            'tuk' => $this->tukAwal($dokumen),
            'tanggal' => $this->info($dokumen)['jadwal']['tanggal'],
        ]);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        $data ??= [];
        $pencapaian = fn ($v) => in_array($v, ['ya', 'tidak'], true) ? $v : null;
        $aspekBersih = fn ($a) => [
            'lingkup' => self::teks($a['lingkup'] ?? null, 500),
            'pertanyaan' => self::teks($a['pertanyaan'] ?? null, 5000),
            'kesesuaian' => self::teks($a['kesesuaian'] ?? null, 500),
        ];

        // Aspek template: salinan tersimpan; bila belum ada, template KT terkini.
        $aspek = array_values(array_filter(array_map($aspekBersih, (array) ($data['aspek'] ?? [])), fn ($a) => $a['pertanyaan'] !== null));
        if ($aspek === []) {
            $aspek = array_values(array_filter(
                array_map($aspekBersih, (array) ($this->template($dokumen)?->instrumenUntuk(MapaTemplate::IA_04B)['aspek'] ?? [])),
                fn ($a) => $a['pertanyaan'] !== null,
            ));
        }

        $jawaban = [];
        foreach ($aspek as $i => $a) {
            $j = $data['jawaban'][$i] ?? [];
            $jawaban[] = [
                'tanggapan' => self::teks($j['tanggapan'] ?? null, 5000),
                'pencapaian' => $pencapaian($j['pencapaian'] ?? null),
                // Kesesuaian dapat diubah asesor; bila belum pernah disimpan, dari template.
                'kesesuaian' => is_array($j) && array_key_exists('kesesuaian', $j) ? self::teks($j['kesesuaian'], 500) : $a['kesesuaian'],
            ];
        }

        return [
            'tuk' => self::tukSah($data['tuk'] ?? null),
            'tanggal' => self::tanggalSah($data['tanggal'] ?? null),
            'judul_kegiatan' => self::teks($data['judul_kegiatan'] ?? null, 500),
            'aspek' => $aspek,
            'jawaban' => $jawaban,
            'tambahan' => collect((array) ($data['tambahan'] ?? []))
                ->map(fn ($t) => [
                    ...$aspekBersih($t),
                    'tanggapan' => self::teks($t['tanggapan'] ?? null, 5000),
                    'pencapaian' => $pencapaian($t['pencapaian'] ?? null),
                ])
                ->filter(fn ($t) => $t['pertanyaan'] !== null)
                ->take(self::MAKS_TAMBAHAN)
                ->values()->all(),
            'rekomendasi_teks' => self::teks($data['rekomendasi_teks'] ?? null, 5000),
            'rekomendasi' => in_array($data['rekomendasi'] ?? null, self::REKOMENDASI, true) ? $data['rekomendasi'] : null,
        ];
    }
}
