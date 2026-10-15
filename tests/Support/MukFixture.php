<?php

namespace Tests\Support;

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\BuktiPersyaratanDasar;
use App\Models\CategorySkemaSertifikasi;
use App\Models\DokumenPenilaian;
use App\Models\ElemenKompetensi;
use App\Models\JadwalAsesmen;
use App\Models\JenisDokumenMuk;
use App\Models\KriteriaUnjukKerja;
use App\Models\PendaftaranSertifikasi;
use App\Models\PenilaianMuk;
use App\Models\SkemaSertifikasi;
use App\Models\TUK;
use App\Models\UnitKompetensi;
use App\Models\User;
use App\Services\PenilaianMukService;
use Database\Seeders\JenisDokumenMukSeeder;
use Illuminate\Support\Str;

/**
 * Membangun rantai data MUK lengkap untuk test: skema -> unit -> elemen -> KUK,
 * pendaftaran (paid) -> jadwal -> penilaian + dokumen APL (lewat PenilaianMukService asli).
 */
class MukFixture
{
    public static function skema(int $jumlahUnit = 2, int $elemenPerUnit = 2): SkemaSertifikasi
    {
        $kategori = CategorySkemaSertifikasi::create([
            'kategori' => ['id' => 'Kategori', 'en' => 'Category'],
            'slug' => 'kategori-'.Str::random(8),
        ]);

        $skema = SkemaSertifikasi::create([
            'category_skema_id' => $kategori->id,
            'title' => ['id' => 'Skema Uji', 'en' => 'Test Scheme'],
            'slug' => 'skema-'.Str::random(8),
            'jenis_skema' => 'KKNI',
            'nomor_skema' => 'SKM-'.Str::random(6),
            'image' => 'skema.png',
            'amount' => 1000000,
        ]);

        foreach (range(1, $jumlahUnit) as $u) {
            $unit = UnitKompetensi::create([
                'skema_sertifikasi_id' => $skema->id,
                'kode_unit' => "U.{$u}.".Str::random(4),
                'judul_unit' => "Unit {$u}",
                'urutan' => $u,
            ]);

            foreach (range(1, $elemenPerUnit) as $e) {
                $elemen = ElemenKompetensi::create([
                    'unit_kompetensi_id' => $unit->id,
                    'nomor_elemen' => $e,
                    'nama_elemen' => "Elemen {$u}.{$e}",
                    'urutan' => $e,
                ]);

                KriteriaUnjukKerja::create([
                    'elemen_kompetensi_id' => $elemen->id,
                    'nomor_kuk' => "{$e}.1",
                    'deskripsi' => "KUK {$u}.{$e}.1",
                    'urutan' => 1,
                ]);
            }
        }

        BuktiPersyaratanDasar::create(['skema_sertifikasi_id' => $skema->id, 'deskripsi' => 'Ijazah', 'urutan' => 1]);

        return $skema;
    }

    public static function penilaian(
        ?Asesi $asesi = null,
        ?Asesor $asesor = null,
        ?SkemaSertifikasi $skema = null,
        string $tanggal = '2026-11-01',
    ): PenilaianMuk {
        if (JenisDokumenMuk::count() === 0) {
            (new JenisDokumenMukSeeder)->run();
        }

        $asesi ??= Asesi::factory()->create();
        $asesor ??= Asesor::factory()->create();
        $skema ??= self::skema();
        $admin = User::factory()->create();

        $tuk = TUK::create(['title' => 'TUK Uji', 'city' => 'Jakarta', 'address' => 'Jl. Uji 1']);

        $pendaftaran = PendaftaranSertifikasi::create([
            'kode_pendaftaran' => (string) Str::uuid(),
            'asesi_id' => $asesi->id,
            'skema_sertifikasi_id' => $skema->id,
            'status' => 'dijadwalkan',
            'harga_snapshot' => 1000000,
            'submitted_at' => now(),
        ]);

        $jadwal = JadwalAsesmen::create([
            'pendaftaran_sertifikasi_id' => $pendaftaran->id,
            'asesor_id' => $asesor->id,
            'tuk_id' => $tuk->id,
            'tanggal_asesmen' => $tanggal,
            'waktu_mulai' => '08:00',
            'dijadwalkan_by' => $admin->id,
            'status' => 'terjadwal',
        ]);

        return app(PenilaianMukService::class)->mulaiDariJadwal($jadwal)->fresh();
    }

    public static function dokumen(PenilaianMuk $penilaian, string $kode): DokumenPenilaian
    {
        return $penilaian->dokumenPenilaian()->kode($kode)->firstOrFail();
    }

    public static function token(Asesi|Asesor $user): string
    {
        $peran = $user instanceof Asesi ? 'asesi' : 'asesor';

        return $user->createToken('muk', ["muk:{$peran}"], now()->addHours(8))->plainTextToken;
    }
}
