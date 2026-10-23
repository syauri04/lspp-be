<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * FR.IA.03 berganti alur: asesor menyiapkan -> asesi menjawab -> asesor menilai.
 * Data lama (asesor mengisi tanggapan & pencapaian sekaligus) dikonversi:
 * - dokumen SELESAI: dipindah ke bentuk baru (jawaban di data, persiapan & penilaian di data_review);
 * - dokumen yang masih berjalan: dikembalikan ke belum_diisi (isian & tanda tangan dihapus) karena
 *   urutan pengisinya berubah.
 * Hanya menyentuh baris FR.IA.03 berformat lama; aman dijalankan ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        $jenisId = DB::table('jenis_dokumen_muks')->where('kode', 'FR.IA.03')->value('id');
        if (! $jenisId) {
            return;
        }

        $disk = Storage::disk(config('muk.tanda_tangan.disk', 'local'));

        DB::table('dokumen_penilaians')->where('jenis_dokumen_muk_id', $jenisId)->orderBy('id')->each(function ($row) use ($disk) {
            $data = json_decode((string) $row->data, true) ?: [];
            if (! array_key_exists('pertanyaan', $data) && ! array_key_exists('umpan_balik', $data)) {
                return; // sudah format baru / belum pernah diisi
            }

            if ($row->status === 'selesai') {
                $tambahan = array_values(array_filter((array) ($data['tambahan'] ?? []), fn ($t) => filled($t['teks'] ?? null)));
                $semua = [...array_values((array) ($data['jawaban'] ?? [])), ...$tambahan];

                DB::table('dokumen_penilaians')->where('id', $row->id)->update([
                    'data' => json_encode(['jawaban' => array_map(fn ($j) => $j['tanggapan'] ?? null, $semua)]),
                    'data_review' => json_encode([
                        'persiapan' => [
                            'tuk' => $data['tuk'] ?? null,
                            'tanggal' => $data['tanggal'] ?? null,
                            'tambahan' => array_map(fn ($t) => $t['teks'], $tambahan),
                            'pertanyaan' => array_values((array) ($data['pertanyaan'] ?? [])),
                            'dibuka_at' => $row->submitted_at ?? $row->updated_at,
                        ],
                        'penilaian' => [
                            'pencapaian' => array_map(fn ($j) => $j['pencapaian'] ?? null, $semua),
                            'umpan_balik' => $data['umpan_balik'] ?? null,
                        ],
                    ]),
                ]);

                return;
            }

            $disk->delete(array_filter([$row->path_tanda_tangan_asesi, $row->path_tanda_tangan_asesor]));
            DB::table('dokumen_penilaians')->where('id', $row->id)->update([
                'status' => 'belum_diisi',
                'data' => null,
                'data_review' => null,
                'snapshot' => null,
                'path_tanda_tangan_asesi' => null,
                'tanggal_tanda_tangan_asesi' => null,
                'path_tanda_tangan_asesor' => null,
                'tanggal_tanda_tangan_asesor' => null,
                'submitted_at' => null,
            ]);
        });
    }

    public function down(): void
    {
        // Konversi data tidak dibalik (format lama tidak lagi didukung kode).
    }
};
