<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\MapaTemplateRencana;
use App\Models\SkemaSertifikasi;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * FR.MAPA.01 Merencanakan Aktivitas dan Proses Asesmen (diisi asesor).
 *
 * - Asesor: §1 Menentukan Pendekatan Asesmen & §3 Persyaratan Modifikasi/Kontekstualisasi
 *   (disimpan di dokumen_penilaians.data).
 * - Otomatis: skema, unit, standar kompetensi (skema), tujuan asesmen (prefill dari APL.01),
 *   §2 rencana asesmen + konfirmasi + penyusun/validator dari template CMS (skema + metode).
 */
class Mapa01Handler extends MapaHandler
{
    public const ASESI = [
        '1' => 'Hasil pelatihan dan/atau pendidikan, dimana kurikulum dan fasilitas praktek mampu telusur terhadap standar kompetensi.',
        '2' => 'Hasil pelatihan dan/atau pendidikan, dimana kurikulum belum berbasis kompetensi.',
        '3' => 'Pekerja berpengalaman, dimana berasal dari industri/tempat kerja yang dalam operasionalnya mampu telusur dengan standar kompetensi.',
        '4' => 'Pekerja berpengalaman, dimana berasal dari industri/tempat kerja yang dalam operasionalnya belum berbasis kompetensi.',
        '5' => 'Pelatihan/belajar mandiri atau otodidak.',
    ];

    public const TUJUAN = [
        'sertifikasi' => 'Sertifikasi',
        'sertifikasi_ulang' => 'Sertifikasi Ulang',
        'pkt' => 'Pengakuan Kompetensi Terkini (PKT)',
        'rpl' => 'Rekognisi Pembelajaran Lampau (RPL)',
        'lainnya' => 'Lainnya',
    ];

    public const LINGKUNGAN = ['nyata' => 'Tempat kerja nyata', 'simulasi' => 'Tempat kerja simulasi'];

    public const PELUANG = ['tersedia' => 'Tersedia', 'terbatas' => 'Terbatas'];

    public const HUBUNGAN = [
        'bukti' => 'Bukti untuk mendukung asesmen:',
        'aktivitas' => 'Aktivitas kerja di tempat kerja asesi:',
        'pembelajaran' => 'Kegiatan pembelajaran:',
    ];

    public const EMOJI = ['senang', 'netral', 'sedih'];

    public const SIAPA = [
        'lsp' => 'Lembaga Sertifikasi Profesi Pariwisata Tiga Puluh Juni',
        'organisasi' => 'Organisasi Pelatihan',
        'asesor_perusahaan' => 'Asesor Perusahaan',
    ];

    public const KONFIRMASI = [
        'manajer' => 'Manajer Sertifikasi LSP Pariwisata Tiga Puluh Juni',
        'master_asesor' => 'Master Asesor/Master Trainer/Lead Asesor Kompetensi',
        'manajer_pelatihan' => 'Manajer Pelatihan Lembaga Training terakreditasi/Lembaga Training terdaftar',
        'supervisor' => 'Manajer atau Supervisor di tempat kerja',
    ];

    public const STANDAR = [
        'kompetensi' => 'Standar Kompetensi:',
        'kurikulum' => 'Kriteria Asesmen dari kurikulum pelatihan',
        'spesifikasi_kinerja' => 'Spesifikasi kinerja suatu perusahaan atau industri',
        'spesifikasi_produk' => 'Spesifikasi produk',
        'pedoman_khusus' => 'Pedoman khusus',
    ];

    public const MODIFIKASI = ['karakteristik', 'kontekstualisasi', 'saran', 'penyesuaian', 'peluang_terintegrasi'];

    private const GRUP_PILIHAN = [
        'asesi' => self::ASESI,
        'tujuan' => self::TUJUAN,
        'lingkungan' => self::LINGKUNGAN,
        'peluang' => self::PELUANG,
        'siapa' => self::SIAPA,
        'konfirmasi' => self::KONFIRMASI,
        'standar' => self::STANDAR,
    ];

    public function kode(): string
    {
        return JenisDokumenMuk::MAPA_01;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        $master = $dokumen->snapshot ?? $this->master($dokumen);
        $prefill = $dokumen->data === null;

        return [
            'isian' => $prefill ? $this->prefill($dokumen) : $this->normalisasi($dokumen->data),
            'prefill' => $prefill,
            'skema' => $master['skema'],
            'unit_kompetensi' => $master['unit_kompetensi'],
            'standar_kompetensi' => $master['standar_kompetensi'],
            'metode' => $dokumen->penilaianMuk->metode_asesmen,
            'rencana' => $master['rencana'],
            'template_lengkap' => $master['template_lengkap'],
            'konfirmasi_orang' => self::tanpaPath($master['penandatangan']['konfirmasi']),
            'penyusun' => self::tanpaPath($master['penandatangan']['penyusun_mapa01']),
            'opsi' => [
                ...collect(self::GRUP_PILIHAN)->map(fn ($opsi) => self::opsi($opsi))->all(),
                'hubungan' => self::opsi(self::HUBUNGAN),
            ],
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        $rules = [
            'isian' => ['required', 'array:'.implode(',', [...array_keys(self::GRUP_PILIHAN), 'hubungan', 'modifikasi'])],
            'isian.hubungan' => ['sometimes', 'array:'.implode(',', array_keys(self::HUBUNGAN))],
            'isian.hubungan.*' => ['array:checked,emoji'],
            'isian.hubungan.*.checked' => ['nullable', 'boolean'],
            'isian.hubungan.*.emoji' => ['nullable', Rule::in(self::EMOJI)],
            'isian.modifikasi' => ['sometimes', 'array:'.implode(',', self::MODIFIKASI)],
            'isian.modifikasi.*' => ['array:ada,catatan'],
            'isian.modifikasi.*.ada' => ['nullable', 'boolean'],
            'isian.modifikasi.*.catatan' => ['nullable', 'string', 'max:3000'],
        ];

        foreach (self::GRUP_PILIHAN as $grup => $opsi) {
            $rules["isian.{$grup}"] = ['sometimes', 'array', 'max:'.count($opsi)];
            $rules["isian.{$grup}.*"] = ['distinct', Rule::in(array_map('strval', array_keys($opsi)))];
        }

        return $rules;
    }

    public function simpan(DokumenPenilaian $dokumen, array $validated, array &$hapusNanti): bool
    {
        $lama = $dokumen->data === null ? $this->prefill($dokumen) : $this->normalisasi($dokumen->data);
        $isian = $validated['isian'];
        // Grup daftar diganti utuh; hubungan & modifikasi digabung per butir.
        $baru = array_replace($lama, Arr::except($isian, ['hubungan', 'modifikasi']));
        foreach (['hubungan', 'modifikasi'] as $grup) {
            foreach ($isian[$grup] ?? [] as $kunci => $nilai) {
                $baru[$grup][$kunci] = array_replace($lama[$grup][$kunci], $nilai);
            }
        }
        $baru = $this->normalisasi($baru);

        if ($baru === $this->normalisasi($dokumen->data ?? [])) {
            return false;
        }

        $dokumen->data = $baru;

        return true;
    }

    public function kesalahanSubmit(DokumenPenilaian $dokumen): array
    {
        $d = $this->normalisasi($dokumen->data ?? []);
        $errors = [];

        $wajib = [
            'asesi' => 'Pilih kategori asesi.',
            'tujuan' => 'Pilih tujuan asesmen.',
            'lingkungan' => 'Pilih lingkungan asesmen.',
            'peluang' => 'Pilih peluang mengumpulkan bukti.',
            'siapa' => 'Pilih siapa yang melakukan asesmen.',
            'konfirmasi' => 'Pilih minimal satu orang yang relevan.',
        ];
        foreach ($wajib as $grup => $pesan) {
            if ($d[$grup] === []) {
                $errors["isian.{$grup}"] = $pesan;
            }
        }

        foreach ($d['hubungan'] as $kunci => $h) {
            if ($h['checked'] && ! $h['emoji']) {
                $errors["isian.hubungan.{$kunci}"] = 'Pilih salah satu emotikon.';
            }
        }

        foreach (self::MODIFIKASI as $kunci) {
            $m = $d['modifikasi'][$kunci];
            if ($m['ada'] === null) {
                $errors["isian.modifikasi.{$kunci}"] = 'Pilih Ada atau tidak ada.';
            } elseif ($m['ada'] && blank($m['catatan'])) {
                $errors["isian.modifikasi.{$kunci}"] = 'Tuliskan keterangannya.';
            }
        }

        if (! $this->master($dokumen)['template_lengkap']) {
            $errors['rencana'] = 'Template MAPA (rencana asesmen per unit) untuk skema & metode ini belum lengkap di CMS. Hubungi admin LSP.';
        }

        return $errors;
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        $master = $this->master($dokumen);
        $master['penandatangan'] = array_map(fn ($baris) => $this->salinTtd($dokumen, $baris), $master['penandatangan']);

        return $master;
    }

    /** Data otomatis: skema, unit, standar kompetensi, template CMS. */
    private function master(DokumenPenilaian $dokumen): array
    {
        $skema = $this->skema($dokumen);
        $template = $this->template($dokumen);
        $rencanaPerUnit = $template?->rencana->keyBy('unit_kompetensi_id') ?? collect();

        $rencana = $skema->unitKompetensis->map(function ($u) use ($rencanaPerUnit) {
            $r = $rencanaPerUnit->get($u->id);

            return [
                'unit_id' => $u->id,
                'judul_unit' => $u->judul_unit,
                'bukti' => $r?->bukti,
                'jenis_l' => (bool) $r?->jenis_l,
                'jenis_tl' => (bool) $r?->jenis_tl,
                'jenis_t' => (bool) $r?->jenis_t,
                ...collect(MapaTemplateRencana::KOLOM_METODE)->mapWithKeys(fn ($k) => [$k => $r?->{$k}])->all(),
            ];
        })->values()->all();

        return [
            'skema' => $this->infoSkema($skema),
            'unit_kompetensi' => $this->daftarUnit($skema),
            'standar_kompetensi' => self::daftarStandar($skema),
            'rencana' => $rencana,
            // Lengkap bila setiap unit punya isian bukti di template.
            'template_lengkap' => $rencana !== [] && collect($rencana)->every(fn ($r) => filled($r['bukti'])),
            'penandatangan' => [
                'konfirmasi' => $this->penandatangan($template, 'konfirmasi'),
                'penyusun_mapa01' => $this->penandatangan($template, 'penyusun_mapa01'),
            ],
        ];
    }

    /** Teks standar kompetensi skema (HTML CMS) -> daftar butir tanpa nomor. */
    private static function daftarStandar(SkemaSertifikasi $skema): array
    {
        $teks = Apl01Handler::teksPolos($skema->standar_kompetensi_kerja);

        return $teks === null ? [] : collect(preg_split('/\n+/', $teks))
            ->map(fn ($b) => trim(preg_replace('/^\s*\d+[.)]\s*/', '', $b)))
            ->filter()
            ->values()
            ->all();
    }

    /** Isi awal: tujuan dari APL.01, LSP & manajer sertifikasi, standar kompetensi, peluang terintegrasi. */
    private function prefill(DokumenPenilaian $dokumen): array
    {
        $apl01 = $dokumen->penilaianMuk->dokumenPenilaian()->kode(JenisDokumenMuk::APL_01)->value('data');
        $apl01 = is_string($apl01) ? json_decode($apl01, true) : $apl01;
        $tujuan = $apl01['tujuan_asesmen'] ?? null;

        $skema = $this->skema($dokumen);
        $daftarUnit = $skema->unitKompetensis->values()
            ->map(fn ($u, $i) => ($i + 1).'. '.$u->judul_unit)->implode("\n");

        return $this->normalisasi([
            'tujuan' => $tujuan && isset(self::TUJUAN[$tujuan]) ? [$tujuan] : [],
            'siapa' => ['lsp'],
            'konfirmasi' => ['manajer'],
            'standar' => ['kompetensi'],
            'modifikasi' => [
                'peluang_terintegrasi' => [
                    'ada' => true,
                    'catatan' => 'Pada Skema '.($skema->title['id'] ?? '').", dengan Unit Kompetensi :\n".$daftarUnit,
                ],
            ],
        ]);
    }

    /** Bentuk isian selalu lengkap & hanya key yang diizinkan. */
    private function normalisasi(?array $data): array
    {
        $data ??= [];
        $hasil = [];

        foreach (self::GRUP_PILIHAN as $grup => $opsi) {
            $hasil[$grup] = array_values(array_intersect(
                array_map('strval', array_keys($opsi)),
                array_map('strval', (array) ($data[$grup] ?? [])),
            ));
        }

        foreach (array_keys(self::HUBUNGAN) as $k) {
            $h = $data['hubungan'][$k] ?? [];
            $hasil['hubungan'][$k] = [
                'checked' => (bool) ($h['checked'] ?? false),
                'emoji' => in_array($h['emoji'] ?? null, self::EMOJI, true) ? $h['emoji'] : null,
            ];
        }

        foreach (self::MODIFIKASI as $k) {
            $m = $data['modifikasi'][$k] ?? [];
            $hasil['modifikasi'][$k] = [
                'ada' => isset($m['ada']) ? (bool) $m['ada'] : null,
                'catatan' => Arr::get($m, 'catatan') ?: null,
            ];
        }

        return $hasil;
    }

    private static function opsi(array $opsi): array
    {
        return collect($opsi)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values()->all();
    }
}
