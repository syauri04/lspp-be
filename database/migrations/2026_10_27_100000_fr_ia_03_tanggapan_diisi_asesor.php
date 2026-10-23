<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FR.IA.03 kembali ke alur "asesor mengisi (tanggapan dicatat asesor) + ttd -> asesi menyetujui".
 * Data alur sebelumnya (persiapan & penilaian di data_review, tanggapan asesi di data.jawaban) dikonversi:
 *   data = { tuk, tanggal, pertanyaan, jawaban: [{tanggapan, pencapaian}], tambahan: [{teks, tanggapan, pencapaian}], umpan_balik }
 * - SELESAI: tetap selesai (ttd asesi & asesor sudah ada).
 * - Masih berjalan (belum_diisi / draft / menunggu_review): menjadi draft milik asesor; tanggapan & penilaian yang
 *   sudah ada dipertahankan; ttd asesi, snapshot & tanggal kirim dikosongkan (asesi menandatangani saat menyetujui).
 * Data & kolom lama disimpan utuh di data_review.arsip_alur_asesi sehingga down() dapat memulihkan.
 * Berkas tanda tangan TIDAK dihapus. Hanya menyentuh baris FR.IA.03 berformat alur sebelumnya; aman dijalankan ulang.
 */
return new class extends Migration
{
    private const KOLOM_ARSIP = ['status', 'snapshot', 'submitted_at', 'path_tanda_tangan_asesi', 'tanggal_tanda_tangan_asesi'];

    public function up(): void
    {
        $jenisId = DB::table('jenis_dokumen_muks')->where('kode', 'FR.IA.03')->value('id');
        if (! $jenisId) {
            return;
        }

        DB::table('dokumen_penilaians')->where('jenis_dokumen_muk_id', $jenisId)->orderBy('id')->each(function ($row) {
            $data = json_decode((string) $row->data, true) ?: [];
            $review = json_decode((string) $row->data_review, true) ?: [];
            $formatLama = isset($review['persiapan']) || isset($review['penilaian'])
                || (isset($data['jawaban'][0]) && ! is_array($data['jawaban'][0]));
            if (! $formatLama || isset($review['arsip_alur_asesi'])) {
                return; // sudah format baru / sudah dikonversi
            }

            $persiapan = (array) ($review['persiapan'] ?? []);
            $penilaian = (array) ($review['penilaian'] ?? []);
            $tanggapan = array_values((array) ($data['jawaban'] ?? []));
            $capai = array_values((array) ($penilaian['pencapaian'] ?? []));
            $pertanyaan = array_values(array_filter((array) ($persiapan['pertanyaan'] ?? []), 'is_string'));
            $tambahan = array_values(array_filter((array) ($persiapan['tambahan'] ?? []), fn ($t) => is_string($t) && trim($t) !== ''));
            $jumlah = count($pertanyaan);

            $baru = [
                'tuk' => $persiapan['tuk'] ?? null,
                'tanggal' => $persiapan['tanggal'] ?? null,
                'pertanyaan' => $pertanyaan,
                'jawaban' => array_map(fn ($i) => ['tanggapan' => $tanggapan[$i] ?? null, 'pencapaian' => $capai[$i] ?? null], array_keys($pertanyaan)),
                'tambahan' => array_map(fn ($t, $j) => [
                    'teks' => $t,
                    'tanggapan' => $tanggapan[$jumlah + $j] ?? null,
                    'pencapaian' => $capai[$jumlah + $j] ?? null,
                ], $tambahan, array_keys($tambahan)),
                'umpan_balik' => $penilaian['umpan_balik'] ?? null,
            ];

            $arsip = ['data' => $data, 'data_review' => $review];
            foreach (self::KOLOM_ARSIP as $kolom) {
                $arsip[$kolom] = $row->{$kolom};
            }
            $sisaReview = array_diff_key($review, ['persiapan' => true, 'penilaian' => true]);
            $ubah = [
                'data' => json_encode($baru, JSON_UNESCAPED_UNICODE),
                'data_review' => json_encode([...$sisaReview, 'arsip_alur_asesi' => $arsip], JSON_UNESCAPED_UNICODE),
            ];

            if ($row->status !== 'selesai') {
                $ubah += [
                    'status' => 'draft',
                    'snapshot' => null,
                    'submitted_at' => null,
                    'path_tanda_tangan_asesi' => null,
                    'tanggal_tanda_tangan_asesi' => null,
                ];
            }

            DB::table('dokumen_penilaians')->where('id', $row->id)->update($ubah);
        });
    }

    public function down(): void
    {
        $jenisId = DB::table('jenis_dokumen_muks')->where('kode', 'FR.IA.03')->value('id');
        if (! $jenisId) {
            return;
        }

        DB::table('dokumen_penilaians')->where('jenis_dokumen_muk_id', $jenisId)->orderBy('id')->each(function ($row) {
            $review = json_decode((string) $row->data_review, true) ?: [];
            $arsip = $review['arsip_alur_asesi'] ?? null;
            if (! is_array($arsip)) {
                return;
            }

            $kembali = [
                'data' => $arsip['data'] === [] ? null : json_encode($arsip['data'], JSON_UNESCAPED_UNICODE),
                'data_review' => $arsip['data_review'] === [] ? null : json_encode($arsip['data_review'], JSON_UNESCAPED_UNICODE),
            ];
            foreach (self::KOLOM_ARSIP as $kolom) {
                $nilai = $arsip[$kolom] ?? null;
                $kembali[$kolom] = $kolom === 'snapshot' && is_array($nilai) ? json_encode($nilai, JSON_UNESCAPED_UNICODE) : $nilai;
            }

            DB::table('dokumen_penilaians')->where('id', $row->id)->update($kembali);
        });
    }
};
