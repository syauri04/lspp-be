<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Katalog dokumen MUK (22 jenis) + dokumen per metode asesmen.
 * Idempoten (upsert): aman dijalankan berulang dan dipanggil dari migration.
 */
class JenisDokumenMukSeeder extends Seeder
{
    /** kode => [nama, kategori, wajib_semua_metode, urutan_default] */
    public const KATALOG = [
        'FR.APL.01' => ['Permohonan Sertifikasi Kompetensi', 'APL', true, 1],
        'FR.APL.02' => ['Asesmen Mandiri', 'APL', true, 2],
        'FR.MAPA.01' => ['Merencanakan Aktivitas dan Proses Asesmen', 'MAPA', true, 3],
        'FR.MAPA.02' => ['Peta Instrumen Asesmen', 'MAPA', true, 4],
        'FR.AK.04' => ['Banding Asesmen', 'AK', true, 5],
        'FR.AK.07' => ['Ceklis Penyesuaian yang Wajar dan Beralasan', 'AK', true, 6],
        'FR.AK.01' => ['Persetujuan Asesmen dan Kerahasiaan', 'AK', true, 7],
        'FR.IA.01' => ['Ceklis Observasi', 'IA', false, 8],
        'FR.IA.02' => ['Tugas Praktik Demonstrasi', 'IA', false, 9],
        'FR.IA.03' => ['Pertanyaan untuk Mendukung Observasi', 'IA', false, 10],
        'FR.IA.04A' => ['Penjelasan Singkat Proyek', 'IA', false, 11],
        'FR.IA.04B' => ['Penilaian Proyek Singkat atau Kegiatan Terstruktur Lainnya', 'IA', false, 12],
        'FR.IA.08' => ['Ceklis Verifikasi Portofolio', 'IA', false, 13],
        'FR.IA.09' => ['Pertanyaan Wawancara', 'IA', false, 14],
        'FR.IA.10' => ['Verifikasi Pihak Ketiga', 'IA', false, 15],
        'FR.IA.06A' => ['Pertanyaan Tertulis Esai', 'IA', true, 16],
        'FR.IA.06B' => ['Lembar Kunci Jawaban Pertanyaan Tertulis Esai', 'IA', true, 17],
        'FR.IA.06C' => ['Lembar Jawaban Pertanyaan Esai', 'IA', true, 18],
        'FR.AK.02' => ['Rekaman Asesmen Kompetensi', 'AK', true, 19],
        'FR.AK.03' => ['Umpan Balik dan Catatan Asesmen', 'AK', true, 20],
        'FR.AK.05' => ['Laporan Asesmen', 'AK', true, 21],
        'FR.AK.06' => ['Meninjau Proses Asesmen', 'AK', true, 22],
    ];

    /** Dokumen yang dibuat saat metode ditetapkan (selain APL), urut sesuai tampilan. */
    public const DOKUMEN_METODE = [
        'kt' => [
            'FR.MAPA.01', 'FR.MAPA.02', 'FR.AK.04', 'FR.AK.07', 'FR.AK.01',
            'FR.IA.04A', 'FR.IA.04B', 'FR.IA.06A', 'FR.IA.06B', 'FR.IA.06C',
            'FR.AK.02', 'FR.AK.03', 'FR.AK.05', 'FR.AK.06',
        ],
        'tpd' => [
            'FR.MAPA.01', 'FR.MAPA.02', 'FR.AK.04', 'FR.AK.07', 'FR.AK.01',
            'FR.IA.01', 'FR.IA.02', 'FR.IA.03', 'FR.IA.06A', 'FR.IA.06B', 'FR.IA.06C',
            'FR.AK.02', 'FR.AK.03', 'FR.AK.05', 'FR.AK.06',
        ],
        'vp' => [
            'FR.MAPA.01', 'FR.MAPA.02', 'FR.AK.04', 'FR.AK.07', 'FR.AK.01',
            'FR.IA.08', 'FR.IA.09', 'FR.IA.10', 'FR.IA.06A', 'FR.IA.06B', 'FR.IA.06C',
            'FR.AK.02', 'FR.AK.03', 'FR.AK.05', 'FR.AK.06',
        ],
    ];

    public function run(): void
    {
        $sekarang = now();

        DB::table('jenis_dokumen_muks')->upsert(
            collect(self::KATALOG)->map(fn ($v, $kode) => [
                'kode' => $kode,
                'nama' => $v[0],
                'kategori' => $v[1],
                'wajib_semua_metode' => $v[2],
                'urutan_default' => $v[3],
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ])->values()->all(),
            ['kode'],
            ['nama', 'kategori', 'wajib_semua_metode', 'urutan_default', 'updated_at'],
        );

        if (! DB::getSchemaBuilder()->hasTable('jenis_dokumen_metode')) {
            return;
        }

        $id = DB::table('jenis_dokumen_muks')->pluck('id', 'kode');
        $baris = [];
        foreach (self::DOKUMEN_METODE as $metode => $kodes) {
            foreach ($kodes as $i => $kode) {
                $baris[] = ['jenis_dokumen_muk_id' => $id[$kode], 'metode' => $metode, 'urutan' => $i + 1];
            }
        }

        DB::table('jenis_dokumen_metode')->upsert($baris, ['jenis_dokumen_muk_id', 'metode'], ['urutan']);
    }
}
