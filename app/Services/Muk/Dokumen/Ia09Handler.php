<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\MapaTemplate;
use App\Services\Muk\TandaTanganService;
use Illuminate\Validation\Rule;

/**
 * FR.IA.09 Pertanyaan Wawancara (metode VP).
 * Wawancara lisan: asesor menuliskan kesimpulan jawaban asesi & pencapaian per pertanyaan,
 * menandatangani; asesi menyetujui dengan tanda tangan. Pertanyaan dari template VP (CMS),
 * disalin ke isian saat pertama disimpan; asesor dapat menambah pertanyaan sendiri.
 * Penyusun & validator dari template VP (dibekukan + salinan ttd saat dikirim).
 *
 * Isian tersimpan: {
 *   tuk, tanggal, bukti: [teks],
 *   pertanyaan: [teks template],                 // server-managed, tidak dari klien
 *   jawaban: [{ kesimpulan, pencapaian }],       // sejajar dengan pertanyaan
 *   tambahan: [{ teks, kesimpulan, pencapaian }]
 * }
 * Isian awal: bukti portofolio = lampiran "bukti yang relevan" FR.APL.01 (berisi berkas/link).
 */
class Ia09Handler extends PersetujuanHandler implements PunyaPenandatangan
{
    use MemakaiTemplatePerangkat;

    public const MAKS_TAMBAHAN = 10;

    public const MAKS_BUKTI = 30;

    public function __construct(protected TandaTanganService $ttd) {}

    public function kode(): string
    {
        return JenisDokumenMuk::IA_09;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        $penyusun = $dokumen->snapshot['penandatangan']['penyusun_ia09'] ?? $this->penandatangan($this->template($dokumen), 'penyusun_ia09');

        return [
            ...$this->payloadDasar($dokumen),
            'penyusun' => self::tanpaPath($penyusun),
            'maks_tambahan' => self::MAKS_TAMBAHAN,
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        $pencapaian = ['nullable', Rule::in(['ya', 'tidak'])];

        return [
            'isian' => ['required', 'array:tuk,tanggal,bukti,jawaban,tambahan'],
            'isian.tuk' => ['nullable', Rule::in(array_keys(self::TUK))],
            'isian.tanggal' => ['nullable', 'date_format:Y-m-d'],
            'isian.bukti' => ['sometimes', 'array', 'max:'.self::MAKS_BUKTI],
            'isian.bukti.*' => ['nullable', 'string', 'max:500'],
            'isian.jawaban' => ['sometimes', 'array', 'max:50'],
            'isian.jawaban.*' => ['array:kesimpulan,pencapaian'],
            'isian.jawaban.*.kesimpulan' => ['nullable', 'string', 'max:5000'],
            'isian.jawaban.*.pencapaian' => $pencapaian,
            'isian.tambahan' => ['sometimes', 'array', 'max:'.self::MAKS_TAMBAHAN],
            'isian.tambahan.*' => ['array:teks,kesimpulan,pencapaian'],
            'isian.tambahan.*.teks' => ['nullable', 'string', 'max:3000'],
            'isian.tambahan.*.kesimpulan' => ['nullable', 'string', 'max:5000'],
            'isian.tambahan.*.pencapaian' => $pencapaian,
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
            $errors['isian.jawaban'] = 'Belum ada pertanyaan. Tambahkan pertanyaan atau minta admin mengisi Template VP di CMS.';
        }
        foreach (['jawaban' => $d['jawaban'], 'tambahan' => $d['tambahan']] as $grup => $baris) {
            foreach ($baris as $i => $j) {
                if ($j['kesimpulan'] === null || $j['pencapaian'] === null) {
                    $errors["isian.{$grup}.{$i}"] = 'Tuliskan kesimpulan jawaban dan pilih pencapaian.';
                }
            }
        }

        return $errors;
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        $baris = $this->penandatangan($this->template($dokumen), 'penyusun_ia09');

        return [...parent::snapshot($dokumen), 'penandatangan' => ['penyusun_ia09' => $this->salinTtd($dokumen, $baris)]];
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        $apl01 = $this->dokumenLain($dokumen, JenisDokumenMuk::APL_01);
        $bukti = $apl01 ? $apl01->buktiRelevan()->get()->filter->terisi()->pluck('nama')->all() : [];

        return $this->normalisasi($dokumen, [
            'tuk' => $this->tukAwal($dokumen),
            'tanggal' => $this->info($dokumen)['jadwal']['tanggal'],
            'bukti' => $bukti,
        ]);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        $data ??= [];
        $pencapaian = fn ($v) => in_array($v, ['ya', 'tidak'], true) ? $v : null;

        // Pertanyaan template: salinan tersimpan; bila belum ada, template VP terkini.
        $pertanyaan = array_values(array_filter(array_map(fn ($t) => self::teks($t, 3000), (array) ($data['pertanyaan'] ?? []))));
        if ($pertanyaan === []) {
            $pertanyaan = array_values(array_filter(array_map(
                fn ($t) => self::teks($t, 3000),
                (array) ($this->template($dokumen)?->instrumenUntuk(MapaTemplate::IA_09)['pertanyaan'] ?? []),
            )));
        }

        $jawaban = [];
        foreach (array_keys($pertanyaan) as $i) {
            $j = $data['jawaban'][$i] ?? [];
            $jawaban[] = ['kesimpulan' => self::teks($j['kesimpulan'] ?? null, 5000), 'pencapaian' => $pencapaian($j['pencapaian'] ?? null)];
        }

        return [
            'tuk' => self::tukSah($data['tuk'] ?? null),
            'tanggal' => self::tanggalSah($data['tanggal'] ?? null),
            'bukti' => array_slice(array_values(array_filter(array_map(fn ($b) => self::teks($b, 500), (array) ($data['bukti'] ?? [])))), 0, self::MAKS_BUKTI),
            'pertanyaan' => $pertanyaan,
            'jawaban' => $jawaban,
            'tambahan' => collect((array) ($data['tambahan'] ?? []))
                ->map(fn ($t) => [
                    'teks' => self::teks($t['teks'] ?? null, 3000),
                    'kesimpulan' => self::teks($t['kesimpulan'] ?? null, 5000),
                    'pencapaian' => $pencapaian($t['pencapaian'] ?? null),
                ])
                ->filter(fn ($t) => $t['teks'] !== null)
                ->take(self::MAKS_TAMBAHAN)
                ->values()->all(),
        ];
    }
}
