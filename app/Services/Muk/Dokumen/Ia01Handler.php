<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\SkemaSertifikasi;
use App\Services\Muk\AsesmenLanjutan;
use Illuminate\Validation\Rule;

/**
 * FR.IA.01 Ceklis Observasi Aktivitas di Tempat Kerja / Tempat Kerja Simulasi (metode TPD).
 * Asesor mengisi pencapaian per elemen, umpan balik & rekomendasi, lalu menandatangani;
 * asesi menyetujui dengan tanda tangan.
 *
 * Struktur unit -> elemen -> KUK diambil dari skema (dibekukan di snapshot saat dikirim).
 * Isian (dokumen_penilaians.data):
 * {
 *   tuk, tanggal,
 *   elemen: { "e{id}": { standar, pencapaian: ya|tidak|null, lanjut } },
 *   umpan_balik, rekomendasi: kompeten|lanjutan|null,
 *   lanjutan: { kelompok, unit, elemen, kuk }
 * }
 * Kunci elemen berawalan "e" agar tidak berubah menjadi indeks numerik di JSON.
 */
class Ia01Handler extends PersetujuanHandler
{
    public const REKOMENDASI = ['kompeten', 'lanjutan'];

    public const LANJUTAN = ['kelompok', 'unit', 'elemen', 'kuk'];

    /** @var array<int, array> struktur per skema (handler dibuat per request). */
    private array $cacheStruktur = [];

    public function kode(): string
    {
        return JenisDokumenMuk::IA_01;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        return [
            ...$this->payloadDasar($dokumen),
            'struktur' => $this->struktur($dokumen),
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:tuk,tanggal,elemen,umpan_balik,rekomendasi,lanjutan'],
            'isian.tuk' => ['nullable', Rule::in(array_keys(self::TUK))],
            'isian.tanggal' => ['nullable', 'date_format:Y-m-d'],
            // Kunci elemen yang tidak dikenal dibuang di normalisasi().
            'isian.elemen' => ['sometimes', 'array', 'max:500'],
            'isian.elemen.*' => ['array:standar,pencapaian,lanjut'],
            'isian.elemen.*.standar' => ['nullable', 'string', 'max:2000'],
            'isian.elemen.*.pencapaian' => ['nullable', Rule::in(['ya', 'tidak'])],
            'isian.elemen.*.lanjut' => ['nullable', 'string', 'max:2000'],
            'isian.umpan_balik' => ['nullable', 'string', 'max:5000'],
            'isian.rekomendasi' => ['nullable', Rule::in(self::REKOMENDASI)],
            'isian.lanjutan' => ['sometimes', 'array:'.implode(',', self::LANJUTAN)],
            'isian.lanjutan.*' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function gabung(array $lama, array $isian): array
    {
        $baru = array_replace($lama, array_diff_key($isian, ['elemen' => true, 'lanjutan' => true]));
        foreach ($isian['elemen'] ?? [] as $kunci => $baris) {
            $baru['elemen'][$kunci] = array_replace($lama['elemen'][$kunci] ?? [], (array) $baris);
        }
        $baru['lanjutan'] = array_replace($lama['lanjutan'], $isian['lanjutan'] ?? []);

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
        if ($d['elemen'] === []) {
            $errors['isian.elemen'] = 'Skema belum memiliki elemen kompetensi. Hubungi admin LSP.';
        }
        foreach ($d['elemen'] as $kunci => $e) {
            if ($e['pencapaian'] === null) {
                $errors["isian.elemen.{$kunci}"] = 'Pilih pencapaian Ya atau Tidak.';
            }
        }

        $adaTidak = collect($d['elemen'])->contains('pencapaian', 'tidak');
        if ($d['rekomendasi'] === null) {
            $errors['isian.rekomendasi'] = 'Pilih rekomendasi asesor.';
        } elseif ($d['rekomendasi'] === 'kompeten' && $adaTidak) {
            $errors['isian.rekomendasi'] = 'Ada elemen yang belum tercapai; asesi belum dapat direkomendasikan kompeten.';
        } elseif ($d['rekomendasi'] === 'lanjutan' && $d['lanjutan']['unit'] === null) {
            $errors['isian.lanjutan'] = 'Tuliskan unit (dan elemen/KUK) yang perlu diases lanjut.';
        }
        if ($d['umpan_balik'] === null) {
            $errors['isian.umpan_balik'] = 'Tuliskan umpan balik untuk asesi.';
        }

        return $errors;
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        return [...parent::snapshot($dokumen), 'struktur' => $this->strukturSkema($dokumen)];
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
        $elemen = [];
        foreach ($this->kunciElemen($dokumen) as $kunci) {
            $e = $data['elemen'][$kunci] ?? [];
            $elemen[$kunci] = [
                'standar' => self::teks($e['standar'] ?? null),
                'pencapaian' => in_array($e['pencapaian'] ?? null, ['ya', 'tidak'], true) ? $e['pencapaian'] : null,
                'lanjut' => self::teks($e['lanjut'] ?? null),
            ];
        }

        $rekomendasi = in_array($data['rekomendasi'] ?? null, self::REKOMENDASI, true) ? $data['rekomendasi'] : null;
        $lanjutan = [];
        foreach (self::LANJUTAN as $k) {
            // Isian lanjutan hanya berlaku bila rekomendasi "lanjutan".
            $lanjutan[$k] = $rekomendasi === 'lanjutan' ? self::teks($data['lanjutan'][$k] ?? null, 1000) : null;
        }

        return [
            'tuk' => self::tukSah($data['tuk'] ?? null),
            'tanggal' => self::tanggalSah($data['tanggal'] ?? null),
            'elemen' => $elemen,
            'umpan_balik' => self::teks($data['umpan_balik'] ?? null, 5000),
            'rekomendasi' => $rekomendasi,
            'lanjutan' => $lanjutan,
        ];
    }

    /** Struktur unit/elemen/KUK: snapshot bila sudah dikirim, selain itu dari skema. */
    private function struktur(DokumenPenilaian $dokumen): array
    {
        return $dokumen->snapshot['struktur'] ?? $this->lingkup($dokumen, $this->strukturSkema($dokumen));
    }

    /**
     * Asesmen lanjutan dari FR.IA.08: hanya unit dalam lingkup; bila KUK unit dipilih, hanya KUK tsb
     * (elemen tanpa KUK tersisa dibuang). Selain itu struktur skema utuh.
     */
    private function lingkup(DokumenPenilaian $dokumen, array $struktur): array
    {
        $lingkup = AsesmenLanjutan::untukDokumen($dokumen);
        if ($lingkup === null) {
            return $struktur;
        }

        return collect($struktur)
            ->filter(fn ($u) => in_array($u['id'], $lingkup['unit'], true))
            ->map(function ($u) use ($lingkup) {
                $kukUnit = collect($u['elemen'])->flatMap(fn ($e) => array_column($e['kuk'], 'id'))->all();
                $pilih = array_values(array_intersect($kukUnit, $lingkup['kuk']));
                if ($pilih !== []) {
                    $u['elemen'] = collect($u['elemen'])
                        ->map(fn ($e) => [...$e, 'kuk' => array_values(array_filter($e['kuk'], fn ($k) => in_array($k['id'], $pilih, true)))])
                        ->filter(fn ($e) => $e['kuk'] !== [])
                        ->values()->all();
                }

                return $u;
            })
            ->values()->all();
    }

    /** @return string[] */
    private function kunciElemen(DokumenPenilaian $dokumen): array
    {
        return collect($this->struktur($dokumen))->flatMap(fn ($u) => array_column($u['elemen'], 'kunci'))->all();
    }

    private function strukturSkema(DokumenPenilaian $dokumen): array
    {
        $skemaId = $dokumen->penilaianMuk->pendaftaran->skema_sertifikasi_id;
        if (isset($this->cacheStruktur[$skemaId])) {
            return $this->cacheStruktur[$skemaId];
        }
        $skema = SkemaSertifikasi::with('unitKompetensis.elemenKompetensi.kriteriaUnjukKerja')->findOrFail($skemaId);

        return $this->cacheStruktur[$skemaId] = $skema->unitKompetensis->map(fn ($u) => [
            'id' => $u->id,
            'kode_unit' => $u->kode_unit,
            'judul_unit' => $u->judul_unit,
            'elemen' => $u->elemenKompetensi->values()->map(fn ($e, $i) => [
                'kunci' => 'e'.$e->id,
                'nomor' => $e->nomor_elemen ?? ($i + 1),
                'nama' => $e->nama_elemen,
                'kuk' => $e->kriteriaUnjukKerja->map(fn ($k) => ['id' => $k->id, 'nomor' => $k->nomor_kuk, 'deskripsi' => $k->deskripsi])->values()->all(),
            ])->all(),
        ])->values()->all();
    }
}
