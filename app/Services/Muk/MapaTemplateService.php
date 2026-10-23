<?php

namespace App\Services\Muk;

use App\Models\MapaTemplate;
use App\Models\MapaTemplatePenandatangan;
use App\Models\MapaTemplateRencana;
use App\Models\SkemaSertifikasi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Template FR.MAPA.01/02 per skema + metode (CMS admin):
 * §2 rencana asesmen per unit, konfirmasi orang yang relevan, penyusun & validator.
 */
class MapaTemplateService
{
    public const METODE_LABEL = [
        'kt' => 'KT – Kegiatan Terstruktur',
        'tpd' => 'TPD – Tugas Praktik Demonstrasi',
        'vp' => 'VP – Verifikasi Portofolio',
        MapaTemplate::UMUM => 'Umum – FR.IA.06 (semua metode)',
    ];

    public function __construct(private TandaTanganService $ttd) {}

    /** Template untuk skema+metode (dibuat bila belum ada, lengkap dengan baris konfirmasi tetap). */
    public function untuk(SkemaSertifikasi $skema, string $metode): MapaTemplate
    {
        $template = MapaTemplate::firstOrCreate(['skema_sertifikasi_id' => $skema->id, 'metode' => $metode]);

        // Baris konfirmasi tetap hanya untuk template MAPA (bukan template umum).
        if ($metode !== MapaTemplate::UMUM && ! $template->penandatangan()->where('bagian', 'konfirmasi')->exists()) {
            foreach (MapaTemplate::PERAN_KONFIRMASI as $i => $peran) {
                $template->penandatangan()->create(['bagian' => 'konfirmasi', 'peran' => $peran, 'urutan' => $i]);
            }
        }

        return $template->load(['rencana', 'penandatangan']);
    }

    /**
     * Isi awal §2 sesuai pola metode (contoh dokumen LSP). TPD belum punya pola baku -> kosong.
     *
     * @return array<string, mixed>|null
     */
    public static function polaDefault(string $metode, string $judulUnit): ?array
    {
        return match ($metode) {
            'vp' => [
                'bukti' => "Hasil Verifikasi Portofolio, Tanya Jawab dan Verifikasi Pihak Ketiga Tentang {$judulUnit}",
                'jenis_l' => false, 'jenis_tl' => true, 'jenis_t' => true,
                'observasi_langsung' => null, 'kegiatan_terstruktur' => null, 'tanya_jawab' => 'PW, DPT',
                'verifikasi_portofolio' => 'CVP', 'reviu_produk' => null, 'verifikasi_pihak_ketiga' => 'VPK',
            ],
            'kt' => [
                'bukti' => "Hasil Kegiatan Terstruktur dan Tanya Jawab Tentang {$judulUnit}",
                'jenis_l' => true, 'jenis_tl' => false, 'jenis_t' => true,
                'observasi_langsung' => null, 'kegiatan_terstruktur' => 'DIT', 'tanya_jawab' => 'DPT',
                'verifikasi_portofolio' => null, 'reviu_produk' => null, 'verifikasi_pihak_ketiga' => null,
            ],
            default => null,
        };
    }

    /**
     * Bersihkan isi instrumen IA dari form: hanya kode & key yang dikenal, teks di-trim,
     * pertanyaan kosong dibuang.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function normalisasiInstrumen(array $instrumen): array
    {
        $teks = fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null;
        $hasil = [];

        if (array_key_exists(MapaTemplate::IA_02, $instrumen)) {
            $hasil[MapaTemplate::IA_02] = [
                'skenario' => $teks($instrumen[MapaTemplate::IA_02]['skenario'] ?? null),
                'waktu' => $teks($instrumen[MapaTemplate::IA_02]['waktu'] ?? null),
            ];
        }
        if (array_key_exists(MapaTemplate::IA_06, $instrumen)) {
            $hasil[MapaTemplate::IA_06] = [
                'waktu' => $teks($instrumen[MapaTemplate::IA_06]['waktu'] ?? null),
                // Soal tanpa pertanyaan dibuang; kunci jawaban opsional per soal.
                'soal' => array_values(array_filter(array_map(fn ($s) => [
                    'pertanyaan' => $teks($s['pertanyaan'] ?? null),
                    'kunci' => $teks($s['kunci'] ?? null),
                ], (array) ($instrumen[MapaTemplate::IA_06]['soal'] ?? [])), fn ($s) => $s['pertanyaan'] !== null)),
            ];
        }
        if (array_key_exists(MapaTemplate::IA_04A, $instrumen)) {
            $hasil[MapaTemplate::IA_04A] = [
                'hal_disiapkan' => $teks($instrumen[MapaTemplate::IA_04A]['hal_disiapkan'] ?? null),
                'hal_didemonstrasikan' => $teks($instrumen[MapaTemplate::IA_04A]['hal_didemonstrasikan'] ?? null),
                // Waktu (poin 1 & 2) dan total waktu; opsional.
                'waktu_pengerjaan' => $teks($instrumen[MapaTemplate::IA_04A]['waktu_pengerjaan'] ?? null),
                'waktu_tanya_jawab' => $teks($instrumen[MapaTemplate::IA_04A]['waktu_tanya_jawab'] ?? null),
                'total_waktu' => $teks($instrumen[MapaTemplate::IA_04A]['total_waktu'] ?? null),
            ];
        }
        if (array_key_exists(MapaTemplate::IA_04B, $instrumen)) {
            // Aspek tanpa pertanyaan dibuang.
            $hasil[MapaTemplate::IA_04B] = [
                'aspek' => array_values(array_filter(array_map(fn ($a) => [
                    'lingkup' => $teks($a['lingkup'] ?? null),
                    'pertanyaan' => $teks($a['pertanyaan'] ?? null),
                    'kesesuaian' => $teks($a['kesesuaian'] ?? null),
                ], (array) ($instrumen[MapaTemplate::IA_04B]['aspek'] ?? [])), fn ($a) => $a['pertanyaan'] !== null)),
            ];
        }
        if (array_key_exists(MapaTemplate::IA_08, $instrumen)) {
            // Nama bukti unik (dipakai sebagai kunci baris di FR.APL.01).
            $hasil[MapaTemplate::IA_08] = [
                'bukti' => array_values(array_unique(array_filter(array_map($teks, (array) ($instrumen[MapaTemplate::IA_08]['bukti'] ?? []))))),
                // Materi/substansi wawancara: id KUK terpilih (kepemilikan skema dicek di controller).
                'materi' => array_values(array_unique(array_filter(array_map('intval', (array) ($instrumen[MapaTemplate::IA_08]['materi'] ?? [])), fn ($id) => $id > 0))),
            ];
        }
        if (array_key_exists(MapaTemplate::IA_09, $instrumen)) {
            $hasil[MapaTemplate::IA_09] = [
                'pertanyaan' => array_values(array_filter(array_map($teks, (array) ($instrumen[MapaTemplate::IA_09]['pertanyaan'] ?? [])))),
            ];
        }
        if (array_key_exists(MapaTemplate::IA_03, $instrumen)) {
            $hasil[MapaTemplate::IA_03] = [
                'pertanyaan' => array_values(array_filter(array_map($teks, (array) ($instrumen[MapaTemplate::IA_03]['pertanyaan'] ?? [])))),
            ];
        }

        return $hasil;
    }

    /**
     * Simpan template dari form CMS.
     *
     * @param  array<int, array<string, mixed>>  $rencana  unit_kompetensi_id => kolom §2
     * @param  array<string, array<int, array<string, mixed>>>  $penandatangan  bagian => baris
     * @param  array<string, array<int, UploadedFile>>  $berkasTtd  bagian => [index => berkas]
     * @param  array<string, array<string, mixed>>|null  $instrumen  kode dokumen IA => isi (null = tidak diubah)
     */
    public function simpan(MapaTemplate $template, array $rencana, array $penandatangan, array $berkasTtd, ?array $instrumen = null): void
    {
        $unitSah = $template->skema->unitKompetensis()->pluck('id')->all();

        // Berkas tanda tangan diproses & ditulis sebelum transaksi (dihapus bila transaksi gagal).
        $berkasBaru = [];
        foreach ($berkasTtd as $bagian => $perBaris) {
            foreach ($perBaris as $i => $file) {
                $path = "mapa-template/{$template->id}/".Str::uuid().'.png';
                Storage::disk($this->ttd->disk())->put($path, $this->ttd->bersihkanBerkas($file, "penandatangan.{$bagian}.{$i}.ttd"));
                $berkasBaru[$bagian][$i] = $path;
            }
        }
        $hapusNanti = [];

        try {
            DB::transaction(function () use ($template, $rencana, $penandatangan, $instrumen, $unitSah, $berkasBaru, &$hapusNanti) {
                if ($instrumen !== null) {
                    $template->instrumen = [...($template->instrumen ?? []), ...self::normalisasiInstrumen($instrumen)];
                    $template->save();
                }

                foreach ($rencana as $unitId => $kolom) {
                    if (! in_array((int) $unitId, $unitSah, true)) {
                        continue; // unit bukan milik skema ini
                    }
                    MapaTemplateRencana::updateOrCreate(
                        ['mapa_template_id' => $template->id, 'unit_kompetensi_id' => (int) $unitId],
                        [
                            'bukti' => $kolom['bukti'] ?? null,
                            'jenis_l' => (bool) ($kolom['jenis_l'] ?? false),
                            'jenis_tl' => (bool) ($kolom['jenis_tl'] ?? false),
                            'jenis_t' => (bool) ($kolom['jenis_t'] ?? false),
                            ...collect(MapaTemplateRencana::KOLOM_METODE)->mapWithKeys(fn ($k) => [$k => $kolom[$k] ?? null])->all(),
                        ],
                    );
                }

                foreach (MapaTemplate::BAGIAN as $bagian) {
                    if (! array_key_exists($bagian, $penandatangan)) {
                        continue;
                    }
                    $lama = $template->penandatangan()->where('bagian', $bagian)->get()->keyBy('id');
                    $dipertahankan = [];

                    foreach (array_values($penandatangan[$bagian]) as $urutan => $baris) {
                        $row = ! empty($baris['id']) ? $lama->get((int) $baris['id']) : null;
                        $row ??= new MapaTemplatePenandatangan(['mapa_template_id' => $template->id, 'bagian' => $bagian]);

                        $row->fill([
                            'peran' => $baris['peran'],
                            'nama' => $baris['nama'] ?? null,
                            'no_reg' => $baris['no_reg'] ?? null,
                            'urutan' => $urutan,
                        ]);

                        $indeksAsli = $baris['_index'];
                        if (isset($berkasBaru[$bagian][$indeksAsli])) {
                            if ($row->path_ttd) {
                                $hapusNanti[] = $row->path_ttd;
                            }
                            $row->path_ttd = $berkasBaru[$bagian][$indeksAsli];
                        } elseif (! empty($baris['hapus_ttd']) && $row->path_ttd) {
                            $hapusNanti[] = $row->path_ttd;
                            $row->path_ttd = null;
                        }

                        $row->save();
                        $dipertahankan[] = $row->id;
                    }

                    foreach ($lama->except($dipertahankan) as $row) {
                        if ($row->path_ttd) {
                            $hapusNanti[] = $row->path_ttd;
                        }
                        $row->delete();
                    }
                }

                $template->touch();
            });
        } catch (\Throwable $e) {
            foreach ($berkasBaru as $perBaris) {
                Storage::disk($this->ttd->disk())->delete(array_values($perBaris));
            }
            throw $e;
        }

        Storage::disk($this->ttd->disk())->delete($hapusNanti);
    }
}
