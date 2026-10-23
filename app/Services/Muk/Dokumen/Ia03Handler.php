<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\MapaTemplate;
use Illuminate\Validation\Rule;

/**
 * FR.IA.03 Pertanyaan untuk Mendukung Observasi (metode TPD; juga asesmen lanjutan dari FR.IA.08).
 * Asesor menyampaikan pertanyaan secara lisan, MENCATAT tanggapan asesi, menilai pencapaian Ya/Tidak,
 * menulis umpan balik, menandatangani; asesi menyetujui dengan tanda tangan (pola PersetujuanHandler).
 * Pertanyaan dari Template TPD (CMS) disalin ke isian saat pertama disimpan (tidak bisa dikirim klien);
 * asesor dapat menambah pertanyaan sendiri.
 *
 * Isian tersimpan: {
 *   tuk, tanggal,
 *   pertanyaan: [teks template],                       // server-managed
 *   jawaban: [{ tanggapan, pencapaian }],              // sejajar dengan pertanyaan
 *   tambahan: [{ teks, tanggapan, pencapaian }],
 *   umpan_balik
 * }
 */
class Ia03Handler extends PersetujuanHandler
{
    use MemakaiTemplatePerangkat;

    public const MAKS_TAMBAHAN = 10;

    public const MAKS_PERTANYAAN = 50;

    public function kode(): string
    {
        return JenisDokumenMuk::IA_03;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        return [
            ...$this->payloadDasar($dokumen),
            'unit_kompetensi' => $dokumen->snapshot['unit_kompetensi'] ?? $this->unitKompetensi($dokumen),
            'maks_tambahan' => self::MAKS_TAMBAHAN,
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        $pencapaian = ['nullable', Rule::in(['ya', 'tidak'])];

        return [
            'isian' => ['required', 'array:tuk,tanggal,jawaban,tambahan,umpan_balik'],
            'isian.tuk' => ['nullable', Rule::in(array_keys(self::TUK))],
            'isian.tanggal' => ['nullable', 'date_format:Y-m-d'],
            'isian.jawaban' => ['sometimes', 'array', 'max:'.self::MAKS_PERTANYAAN],
            'isian.jawaban.*' => ['array:tanggapan,pencapaian'],
            'isian.jawaban.*.tanggapan' => ['nullable', 'string', 'max:5000'],
            'isian.jawaban.*.pencapaian' => $pencapaian,
            'isian.tambahan' => ['sometimes', 'array', 'max:'.self::MAKS_TAMBAHAN],
            'isian.tambahan.*' => ['array:teks,tanggapan,pencapaian'],
            'isian.tambahan.*.teks' => ['nullable', 'string', 'max:2000'],
            'isian.tambahan.*.tanggapan' => ['nullable', 'string', 'max:5000'],
            'isian.tambahan.*.pencapaian' => $pencapaian,
            'isian.umpan_balik' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** Pertanyaan template selalu dari isian lama (tidak bisa ditimpa klien). */
    protected function gabung(array $lama, array $isian): array
    {
        return [...array_replace($lama, $isian), 'pertanyaan' => $lama['pertanyaan']];
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
        if ($d['pertanyaan'] === [] && $d['tambahan'] === []) {
            $errors['isian.jawaban'] = 'Belum ada pertanyaan. Tambahkan pertanyaan atau minta admin mengisi Template TPD di CMS.';
        }
        foreach (['jawaban' => $d['jawaban'], 'tambahan' => $d['tambahan']] as $grup => $baris) {
            foreach ($baris as $i => $j) {
                if ($j['tanggapan'] === null || $j['pencapaian'] === null) {
                    $errors["isian.{$grup}.{$i}"] = 'Catat tanggapan asesi dan pilih pencapaian Ya/Tidak.';
                }
            }
        }
        if ($d['umpan_balik'] === null) {
            $errors['isian.umpan_balik'] = 'Tuliskan umpan balik untuk asesi.';
        }

        return $errors;
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        return [...parent::snapshot($dokumen), 'unit_kompetensi' => $this->unitKompetensi($dokumen)];
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

        // Pertanyaan template: salinan tersimpan; bila belum ada, Template TPD terkini.
        $pertanyaan = array_values(array_filter(array_map(fn ($t) => self::teks($t, 2000), (array) ($data['pertanyaan'] ?? []))));
        if ($pertanyaan === []) {
            $pertanyaan = array_values(array_filter(array_map(
                fn ($t) => self::teks($t, 2000),
                (array) ($this->template($dokumen)?->instrumenUntuk(MapaTemplate::IA_03)['pertanyaan'] ?? []),
            )));
        }
        $pertanyaan = array_slice($pertanyaan, 0, self::MAKS_PERTANYAAN);

        $jawaban = [];
        foreach (array_keys($pertanyaan) as $i) {
            $j = $data['jawaban'][$i] ?? [];
            $jawaban[] = [
                'tanggapan' => self::teks(is_array($j) ? ($j['tanggapan'] ?? null) : null, 5000),
                'pencapaian' => $pencapaian(is_array($j) ? ($j['pencapaian'] ?? null) : null),
            ];
        }

        return [
            'tuk' => self::tukSah($data['tuk'] ?? null),
            'tanggal' => self::tanggalSah($data['tanggal'] ?? null),
            'pertanyaan' => $pertanyaan,
            'jawaban' => $jawaban,
            'tambahan' => collect((array) ($data['tambahan'] ?? []))
                ->filter(fn ($t) => is_array($t))
                ->map(fn ($t) => [
                    'teks' => self::teks($t['teks'] ?? null, 2000),
                    'tanggapan' => self::teks($t['tanggapan'] ?? null, 5000),
                    'pencapaian' => $pencapaian($t['pencapaian'] ?? null),
                ])
                ->filter(fn ($t) => $t['teks'] !== null)
                ->take(self::MAKS_TAMBAHAN)
                ->values()->all(),
            'umpan_balik' => self::teks($data['umpan_balik'] ?? null, 5000),
        ];
    }
}
