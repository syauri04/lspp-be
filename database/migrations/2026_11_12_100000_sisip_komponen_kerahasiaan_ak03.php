<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FR.AK.03 mendapat komponen ke-9 "jaminan kerahasiaan hasil asesmen" (sesuai formulir asli), sebelum
 * butir terakhir "keterampilan komunikasi". Jawaban disimpan per indeks, jadi data lama (9 butir)
 * disisipi baris kosong di indeks 8 agar jawaban butir terakhir tidak tertukar.
 * Idempoten: hanya data yang masih 9 butir. Dokumen yang sudah dikirim tetap utuh; butir baru tampil kosong
 * (memang tidak ditanyakan saat itu).
 */
return new class extends Migration
{
    private const INDEKS_BARU = 8;

    public function up(): void
    {
        $this->ubah(function (array $komponen) {
            if (count($komponen) !== 9) {
                return null;
            }
            array_splice($komponen, self::INDEKS_BARU, 0, [['hasil' => null, 'catatan' => null]]);

            return $komponen;
        });
    }

    public function down(): void
    {
        $this->ubah(function (array $komponen) {
            if (count($komponen) !== 10) {
                return null;
            }
            array_splice($komponen, self::INDEKS_BARU, 1);

            return $komponen;
        });
    }

    /** @param  callable(array): ?array  $fn */
    private function ubah(callable $fn): void
    {
        $jenisId = DB::table('jenis_dokumen_muks')->where('kode', 'FR.AK.03')->value('id');
        if (! $jenisId) {
            return;
        }

        DB::table('dokumen_penilaians')->where('jenis_dokumen_muk_id', $jenisId)->whereNotNull('data')
            ->orderBy('id')->select(['id', 'data'])
            ->chunkById(200, function ($baris) use ($fn) {
                foreach ($baris as $b) {
                    $data = json_decode($b->data, true);
                    if (! is_array($data) || ! is_array($data['komponen'] ?? null)) {
                        continue;
                    }
                    $baru = $fn(array_values($data['komponen']));
                    if ($baru === null) {
                        continue;
                    }
                    $data['komponen'] = $baru;
                    DB::table('dokumen_penilaians')->where('id', $b->id)->update(['data' => json_encode($data)]);
                }
            });
    }
};
