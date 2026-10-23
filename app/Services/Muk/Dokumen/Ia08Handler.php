<?php

namespace App\Services\Muk\Dokumen;

use App\Models\Apl01BuktiRelevan;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\MapaTemplate;
use App\Models\SkemaSertifikasi;
use App\Services\Muk\AsesmenLanjutan;
use Illuminate\Validation\Rule;

/**
 * FR.IA.08 Ceklis Verifikasi Portofolio (metode VP).
 * Asesor memverifikasi bukti portofolio terhadap aturan bukti (valid/asli/terkini/memadai),
 * mencentang UNIT kompetensi yang perlu diklarifikasi saat wawancara (IA.09), mengisi bukti tambahan &
 * rekomendasi, menandatangani; asesi menyetujui dengan tanda tangan.
 *
 * Isian: {
 *   tuk, tanggal,
 *   portofolio: [{ bukti, relevan_id, valid, asli, terkini, memadai }],   // ya|tidak|null; relevan_id = lampiran APL.01
 *   klarifikasi: ["u{unitId}"],                  // satu centang per unit kompetensi
 *   materi: ["k{kukId}"],                        // materi/substansi wawancara (KUK) yang ditampilkan per unit;
 *                                                // awal dari Template VP (CMS), dapat ditambah/hapus asesor
 *   bukti_tambahan, rekomendasi: kompeten|lanjutan,
 *   lanjutan: { jenis: observasi|kegiatan_terstruktur, unit: ["u{unitId}"], kuk: ["k{kukId}"] }
 *     // lingkup asesmen lanjutan; KUK kosong untuk suatu unit = seluruh KUK unit tsb.
 * Saat selesai dengan rekomendasi lanjutan, dokumen metode TPD (observasi) / KT (kegiatan terstruktur)
 * dibuat untuk lingkup tsb (lihat AsesmenLanjutan).
 * }
 * Isian awal: lampiran "bukti yang relevan" FR.APL.01 yang berisi berkas/link (butir dari Template VP di CMS);
 * asesor dapat membuka berkasnya (payload `lampiran`) sebelum menilai aturan bukti.
 */
class Ia08Handler extends PersetujuanHandler implements SetelahSelesai
{
    public const ATURAN = ['valid', 'asli', 'terkini', 'memadai'];

    public const MAKS_PORTOFOLIO = 30;

    /** @var array<int, array> daftar KUK per skema (handler dibuat per request). */
    private array $cacheKuk = [];

    public function kode(): string
    {
        return JenisDokumenMuk::IA_08;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        return [
            ...$this->payloadDasar($dokumen),
            'kuk' => $this->kuk($dokumen),
            'lampiran' => $this->lampiran($dokumen),
            // Pilihan lingkup asesmen lanjutan (kode & judul unit).
            'unit_kompetensi' => $dokumen->snapshot['unit_kompetensi'] ?? $this->unitKompetensi($dokumen),
            'maks_portofolio' => self::MAKS_PORTOFOLIO,
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:tuk,tanggal,portofolio,klarifikasi,materi,bukti_tambahan,rekomendasi,lanjutan'],
            'isian.tuk' => ['nullable', Rule::in(array_keys(self::TUK))],
            'isian.tanggal' => ['nullable', 'date_format:Y-m-d'],
            'isian.portofolio' => ['sometimes', 'array', 'max:'.self::MAKS_PORTOFOLIO],
            'isian.portofolio.*' => ['array:bukti,relevan_id,'.implode(',', self::ATURAN)],
            'isian.portofolio.*.relevan_id' => ['nullable', 'integer'],
            'isian.portofolio.*.bukti' => ['nullable', 'string', 'max:500'],
            ...collect(self::ATURAN)->mapWithKeys(fn ($a) => ["isian.portofolio.*.{$a}" => ['nullable', Rule::in(['ya', 'tidak'])]])->all(),
            // Kunci KUK tak dikenal dibuang di normalisasi().
            'isian.klarifikasi' => ['sometimes', 'array', 'max:1000'],
            'isian.klarifikasi.*' => ['string', 'max:20'],
            // Kunci KUK "k{id}"; kunci di luar skema dibuang di normalisasi().
            'isian.materi' => ['sometimes', 'array', 'max:1000'],
            'isian.materi.*' => ['string', 'max:20'],
            'isian.bukti_tambahan' => ['nullable', 'string', 'max:5000'],
            'isian.rekomendasi' => ['nullable', Rule::in(Ia01Handler::REKOMENDASI)],
            'isian.lanjutan' => ['sometimes', 'array:jenis,unit,kuk'],
            'isian.lanjutan.jenis' => ['nullable', Rule::in(array_keys(AsesmenLanjutan::JENIS))],
            // Kunci unit/KUK di luar skema dibuang di normalisasi().
            'isian.lanjutan.unit' => ['sometimes', 'array', 'max:200'],
            'isian.lanjutan.unit.*' => ['string', 'max:20'],
            'isian.lanjutan.kuk' => ['sometimes', 'array', 'max:1000'],
            'isian.lanjutan.kuk.*' => ['string', 'max:20'],
        ];
    }

    protected function gabung(array $lama, array $isian): array
    {
        $baru = array_replace($lama, array_diff_key($isian, ['lanjutan' => true]));
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
        if ($d['portofolio'] === []) {
            $errors['isian.portofolio'] = 'Tuliskan minimal satu bukti portofolio.';
        }
        foreach ($d['portofolio'] as $i => $p) {
            if (collect(self::ATURAN)->contains(fn ($a) => $p[$a] === null)) {
                $errors["isian.portofolio.{$i}"] = 'Lengkapi verifikasi aturan bukti (Ya/Tidak) untuk bukti ini.';
            }
        }
        if ($d['rekomendasi'] === null) {
            $errors['isian.rekomendasi'] = 'Pilih rekomendasi asesor.';
        } elseif ($d['rekomendasi'] === 'lanjutan') {
            if ($d['lanjutan']['jenis'] === null) {
                $errors['isian.lanjutan.jenis'] = 'Pilih observasi langsung atau kegiatan terstruktur.';
            }
            if ($d['lanjutan']['unit'] === []) {
                $errors['isian.lanjutan.unit'] = 'Pilih minimal satu unit kompetensi yang perlu diases lanjut.';
            }
        }

        return $errors;
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        return [...parent::snapshot($dokumen), 'kuk' => $this->daftarKuk($dokumen), 'unit_kompetensi' => $this->unitKompetensi($dokumen)];
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        return $this->normalisasi($dokumen, [
            'tuk' => $this->tukAwal($dokumen),
            'tanggal' => $this->info($dokumen)['jadwal']['tanggal'],
            'portofolio' => array_map(fn ($b) => ['bukti' => $b['nama'], 'relevan_id' => $b['id']], $this->buktiRelevan($dokumen)),
        ]);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        $data ??= [];
        $pilih = fn ($v) => in_array($v, ['ya', 'tidak'], true) ? $v : null;

        // relevan_id hanya dipertahankan bila memang lampiran FR.APL.01 penilaian ini.
        $lampiranSah = array_column($this->lampiran($dokumen), 'id');
        $portofolio = collect((array) ($data['portofolio'] ?? []))
            ->map(fn ($p) => [
                'bukti' => self::teks($p['bukti'] ?? null, 500),
                'relevan_id' => in_array((int) ($p['relevan_id'] ?? 0), $lampiranSah, true) ? (int) $p['relevan_id'] : null,
                ...collect(self::ATURAN)->mapWithKeys(fn ($a) => [$a => $pilih($p[$a] ?? null)])->all(),
            ])
            ->filter(fn ($p) => $p['bukti'] !== null)
            ->take(self::MAKS_PORTOFOLIO)
            ->values()->all();

        $kuk = $this->kuk($dokumen);
        $unitSah = array_values(array_unique(array_column($kuk, 'unit_kunci')));
        // Kompatibel data lama (centang per KUK "k{id}") -> dipetakan ke unitnya.
        $kukKeUnit = array_column($kuk, 'unit_kunci', 'kunci');
        $dipilih = array_map(fn ($k) => $kukKeUnit[$k] ?? $k, (array) ($data['klarifikasi'] ?? []));
        $rekomendasi = in_array($data['rekomendasi'] ?? null, Ia01Handler::REKOMENDASI, true) ? $data['rekomendasi'] : null;

        return [
            'tuk' => self::tukSah($data['tuk'] ?? null),
            'tanggal' => self::tanggalSah($data['tanggal'] ?? null),
            'portofolio' => $portofolio,
            // Urut sesuai struktur skema.
            'klarifikasi' => array_values(array_intersect($unitSah, $dipilih)),
            // Belum pernah diatur (dokumen baru / data lama) -> materi dari Template VP. Urut sesuai struktur skema.
            'materi' => array_values(array_intersect(
                array_column($kuk, 'kunci'),
                array_key_exists('materi', $data) ? (array) $data['materi'] : $this->materiTemplate($dokumen),
            )),
            'bukti_tambahan' => self::teks($data['bukti_tambahan'] ?? null, 5000),
            'rekomendasi' => $rekomendasi,
            'lanjutan' => $this->lanjutanSah($kuk, $rekomendasi === 'lanjutan' ? (array) ($data['lanjutan'] ?? []) : []),
        ];
    }

    /** KUK dari snapshot (dokumen terkirim) atau skema; snapshot lama tanpa unit_kunci dilengkapi dari nomor unit. */
    private function kuk(DokumenPenilaian $dokumen): array
    {
        $kuk = $dokumen->snapshot['kuk'] ?? null;
        if ($kuk === null) {
            return $this->daftarKuk($dokumen);
        }

        return array_map(fn ($k) => [...$k, 'unit_kunci' => $k['unit_kunci'] ?? 'n'.$k['unit_no']], $kuk);
    }

    /**
     * Semua KUK skema: kunci "k{id}", unit_kunci "u{unitId}" (kunci centang), nomor unit/elemen untuk tabel klarifikasi.
     *
     * @return array<int, array{kunci: string, unit_no: int, elemen_no: string|int, nomor: ?string, deskripsi: string}>
     */
    private function daftarKuk(DokumenPenilaian $dokumen): array
    {
        $skemaId = $dokumen->penilaianMuk->pendaftaran->skema_sertifikasi_id;
        if (isset($this->cacheKuk[$skemaId])) {
            return $this->cacheKuk[$skemaId];
        }
        $skema = SkemaSertifikasi::with('unitKompetensis.elemenKompetensi.kriteriaUnjukKerja')->findOrFail($skemaId);

        $hasil = [];
        foreach ($skema->unitKompetensis->values() as $u => $unit) {
            foreach ($unit->elemenKompetensi->values() as $e => $elemen) {
                foreach ($elemen->kriteriaUnjukKerja as $kuk) {
                    $hasil[] = [
                        'kunci' => 'k'.$kuk->id,
                        'unit_kunci' => 'u'.$unit->id,
                        'unit_no' => $u + 1,
                        'elemen_no' => $elemen->nomor_elemen ?? ($e + 1),
                        'nomor' => $kuk->nomor_kuk,
                        'deskripsi' => $kuk->deskripsi,
                    ];
                }
            }
        }

        return $this->cacheKuk[$skemaId] = $hasil;
    }

    /** FR.IA.08 selesai dengan rekomendasi lanjutan -> buat dokumen TPD/KT untuk lingkup terpilih. */
    public function setelahSelesai(DokumenPenilaian $dokumen): void
    {
        app(AsesmenLanjutan::class)->terapkan($dokumen->penilaianMuk);
    }

    /**
     * Lingkup lanjutan sah: unit milik skema; KUK hanya dari unit terpilih. Urut sesuai struktur skema.
     * Data lama (teks bebas kelompok/unit/elemen/kuk) tidak dikenali -> kosong.
     */
    private function lanjutanSah(array $kuk, array $l): array
    {
        $jenis = is_string($l['jenis'] ?? null) && isset(AsesmenLanjutan::JENIS[$l['jenis']]) ? $l['jenis'] : null;
        $unit = array_values(array_intersect(array_values(array_unique(array_column($kuk, 'unit_kunci'))), is_array($l['unit'] ?? null) ? $l['unit'] : []));
        $kukUnit = array_column(array_filter($kuk, fn ($k) => in_array($k['unit_kunci'], $unit, true)), 'kunci');

        return [
            'jenis' => $jenis,
            'unit' => $unit,
            'kuk' => array_values(array_intersect($kukUnit, is_array($l['kuk'] ?? null) ? $l['kuk'] : [])),
        ];
    }

    /** Materi/substansi wawancara dari Template VP (CMS): kunci KUK "k{id}". */
    private function materiTemplate(DokumenPenilaian $dokumen): array
    {
        $template = MapaTemplate::query()
            ->where('skema_sertifikasi_id', $dokumen->penilaianMuk->pendaftaran->skema_sertifikasi_id)
            ->where('metode', 'vp')
            ->first();

        return array_map(fn ($id) => 'k'.(int) $id, (array) ($template?->instrumenUntuk(MapaTemplate::IA_08)['materi'] ?? []));
    }

    /** Lampiran bukti yang relevan FR.APL.01 yang berisi berkas/link (urut template lalu tambahan asesi). */
    private function lampiran(DokumenPenilaian $dokumen): array
    {
        $apl01 = $this->dokumenLain($dokumen, JenisDokumenMuk::APL_01);

        return $apl01
            ? $apl01->buktiRelevan()->get()
                ->filter(fn (Apl01BuktiRelevan $b) => $b->terisi())
                ->map(fn (Apl01BuktiRelevan $b) => Apl01Handler::barisRelevan($b))
                ->values()->all()
            : [];
    }

    /** Isian awal portofolio: lampiran bukti relevan FR.APL.01 (maks MAKS_PORTOFOLIO). */
    private function buktiRelevan(DokumenPenilaian $dokumen): array
    {
        return array_slice($this->lampiran($dokumen), 0, self::MAKS_PORTOFOLIO);
    }
}
