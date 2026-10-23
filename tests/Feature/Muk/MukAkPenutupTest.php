<?php

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\PenilaianMuk;
use App\Models\RiwayatDokumenPenilaian;
use App\Services\Muk\MetodeAsesmenService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MukFixture;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

/**
 * Penilaian TPD: semua dokumen sebelum penutup selesai; IA.01 punya 1 elemen "tidak".
 *
 * @return array{0: PenilaianMuk, 1: string, 2: string}
 */
function penutupSiap(bool $iaSelesai = true): array
{
    $asesor = Asesor::factory()->create();
    $asesi = Asesi::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);

    MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_admin' => 'diterima']);
    MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_asesor' => 'dapat_dilanjutkan']);
    app(MetodeAsesmenService::class)->tetapkan($p, 'tpd', $asesor);

    $p->dokumenPenilaian()->with('jenisDokumen')->get()
        ->reject(fn ($d) => in_array($d->jenisDokumen->kode, ['FR.AK.02', 'FR.AK.03', 'FR.AK.04', 'FR.AK.05', 'FR.AK.06'], true))
        ->each(fn ($d) => $d->update(['status' => DokumenPenilaian::STATUS_SELESAI]));
    MukFixture::dokumen($p, JenisDokumenMuk::AK_01)->update(['data' => ['tuk' => 'sewaktu']]);
    MukFixture::dokumen($p, JenisDokumenMuk::IA_01)->update(['data' => ['elemen' => ['e1' => ['pencapaian' => 'tidak'], 'e2' => ['pencapaian' => 'ya']]]]);
    if (! $iaSelesai) {
        MukFixture::dokumen($p, JenisDokumenMuk::IA_06C)->update(['status' => DokumenPenilaian::STATUS_DRAFT]);
    }

    // Metode ditetapkan -> jadwal CMS "berlangsung".
    expect($p->jadwalAsesmen->fresh()->status)->toBe('berlangsung');

    return [$p->fresh(), MukFixture::token($asesor), MukFixture::token($asesi)];
}

function akunBaru(): void
{
    app('auth')->forgetGuards();
}

/** Asesor isi + ttd + kirim AK.02, asesi setujui. */
function ak02Selesai($test, PenilaianMuk $p, string $token, string $tokenAsesi, string $rekomendasi): void
{
    $url = "/api/muk/penilaian/{$p->id}/dokumen/ak-02";
    $test->withToken($token)->patchJson($url, ['isian' => [
        'rekomendasi' => $rekomendasi,
        'tindak_lanjut' => $rekomendasi === 'belum_kompeten' ? 'Ulang unit 1' : null,
        'komentar' => 'Didukung jawaban lisan & tertulis.',
    ]])->assertOk();
    $test->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
    $test->withToken($token)->postJson("{$url}/submit")->assertOk();
    akunBaru();
    $test->withToken($tokenAsesi)->postJson("{$url}/setujui", ['tanda_tangan' => pngDataUrl()])->assertOk();
    akunBaru();
}

describe('FR.AK.02', function () {
    it('terkunci sampai semua IA selesai', function () {
        [$p, $token] = penutupSiap(iaSelesai: false);

        $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/ak-02")
            ->assertForbidden()
            ->assertJsonPath('message', 'Selesaikan semua dokumen IA terlebih dahulu (FR.IA.06C).');
    });

    it('isian awal bukti per unit & indikasi; validasi keputusan; alur persetujuan', function () {
        [$p, $token, $tokenAsesi] = penutupSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ak-02";

        $res = $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.isian.tuk', 'sewaktu')
            ->assertJsonPath('data.indikasi.0.dokumen', 'FR.IA.01')
            ->assertJsonCount(2, 'data.unit_kompetensi');
        $unit = 'u'.$res->json('data.unit_kompetensi.0.id');
        expect($res->json("data.isian.bukti.{$unit}"))->toEqualCanonicalizing(['observasi', 'lisan', 'tertulis']);

        $this->withToken($token)->patchJson($url, ['isian' => ['rekomendasi' => 'kompeten', 'bukti' => [$unit => []]]])->assertOk();
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors(["isian.bukti.{$unit}", 'isian.komentar']);

        $this->withToken($token)->patchJson($url, ['isian' => ['rekomendasi' => 'belum_kompeten', 'bukti' => [$unit => ['observasi']]]])->assertOk();
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors('isian.tindak_lanjut');

        akunBaru();
        $this->withToken($tokenAsesi)->getJson("/api/muk/penilaian/{$p->id}/dokumen/ak-03")
            ->assertForbidden()
            ->assertJsonPath('message', 'Menunggu keputusan asesmen (FR.AK.02) selesai.');
        akunBaru();

        ak02Selesai($this, $p, $token, $tokenAsesi, 'belum_kompeten');
        expect(MukFixture::dokumen($p, JenisDokumenMuk::AK_02)->status)->toBe(DokumenPenilaian::STATUS_SELESAI);
    });
});

describe('dokumen penutup & penyelesaian penilaian', function () {
    it('AK.03 diisi asesi; AK.05 & AK.06 hanya asesor; semua selesai -> status penilaian mengikuti AK.02', function () {
        [$p, $token, $tokenAsesi] = penutupSiap();
        ak02Selesai($this, $p, $token, $tokenAsesi, 'belum_kompeten');
        $base = "/api/muk/penilaian/{$p->id}/dokumen";

        // AK.03: asesi mengisi; asesor belum bisa melihat sebelum dikirim.
        $this->withToken($token)->getJson("{$base}/ak-03")->assertForbidden();
        akunBaru();
        $this->withToken($tokenAsesi)->getJson("{$base}/ak-03")
            ->assertOk()
            ->assertJsonPath('data.header.tuk', 'sewaktu')
            ->assertJsonCount(9, 'data.komponen');
        $komponen = array_fill(0, 9, ['hasil' => 'ya']);
        $komponen[3] = ['hasil' => null];
        $this->withToken($tokenAsesi)->patchJson("{$base}/ak-03", ['isian' => ['komponen' => $komponen]])->assertOk();
        $this->withToken($tokenAsesi)->postJson("{$base}/ak-03/submit")->assertJsonValidationErrors('isian.komponen.3');
        $komponen[3] = ['hasil' => 'tidak', 'catatan' => 'Kurang dijelaskan'];
        $this->withToken($tokenAsesi)->patchJson("{$base}/ak-03", ['isian' => ['komponen' => $komponen]])->assertOk();
        $this->withToken($tokenAsesi)->postJson("{$base}/ak-03/submit")->assertOk()->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_SELESAI);

        // AK.05 & AK.06: hanya asesor.
        $this->withToken($tokenAsesi)->getJson("{$base}/ak-05")->assertForbidden()->assertJsonPath('message', 'Dokumen ini hanya untuk asesor.');
        $this->withToken($tokenAsesi)->getJson("{$base}/ak-06")->assertForbidden();
        akunBaru();
        $this->withToken($token)->getJson("{$base}/ak-03")->assertOk();

        $this->withToken($token)->getJson("{$base}/ak-05")
            ->assertOk()
            ->assertJsonPath('data.rekomendasi', 'belum_kompeten')
            ->assertJsonPath('data.isian.keterangan', 'Ulang unit 1');
        $this->withToken($token)->patchJson("{$base}/ak-05", ['isian' => ['keterangan' => '', 'aspek' => 'Positif']])->assertOk();
        $this->withToken($token)->postJson("{$base}/ak-05/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$base}/ak-05/submit")->assertJsonValidationErrors('isian.keterangan');
        $this->withToken($token)->patchJson("{$base}/ak-05", ['isian' => ['keterangan' => 'U.1 Unit 1']])->assertOk();
        $this->withToken($token)->postJson("{$base}/ak-05/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$base}/ak-05/submit")->assertOk()->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_SELESAI);

        $res = $this->withToken($token)->getJson("{$base}/ak-06")->assertOk();
        expect($res->json('data.isian.dimensi.task_skills'))->toContain('CL (FR.IA.01)')
            ->and($res->json('data.isian.nama_peninjau'))->toBe($p->jadwalAsesmen->asesor->nama);
        // "Fleksibel" tidak berlaku untuk keputusan asesmen -> dibuang.
        $this->withToken($token)->patchJson("{$base}/ak-06", ['isian' => ['prinsip' => ['keputusan' => ['validitas', 'fleksibel']]]])
            ->assertOk()
            ->assertJsonPath('data.isian.prinsip.keputusan', ['validitas']);
        expect(PenilaianMuk::find($p->id)->status)->toBe(PenilaianMuk::STATUS_BERLANGSUNG);

        $this->withToken($token)->postJson("{$base}/ak-06/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$base}/ak-06/submit")->assertOk();

        // Semua dokumen (kecuali AK.04 banding) selesai -> penilaian selesai sesuai AK.02.
        $p->refresh();
        expect($p->status)->toBe(PenilaianMuk::STATUS_SELESAI_BELUM_KOMPETEN)
            ->and($p->selesai_at)->not->toBeNull()
            ->and(MukFixture::dokumen($p, JenisDokumenMuk::AK_04)->status)->toBe(DokumenPenilaian::STATUS_BELUM_DIISI)
            ->and(RiwayatDokumenPenilaian::where('aksi', 'penilaian_selesai')->exists())->toBeTrue();
        // Jadwal & pendaftaran (CMS/website utama) ikut selesai.
        expect($p->jadwalAsesmen->fresh()->status)->toBe('selesai')
            ->and($p->pendaftaran->fresh()->status)->toBe('asesmen_selesai')
            ->and($p->pendaftaran->riwayatStatus()->where('status_ke', 'asesmen_selesai')->value('keterangan'))->toContain('Belum Kompeten');
        $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}")->assertJsonPath('data.status', PenilaianMuk::STATUS_SELESAI_BELUM_KOMPETEN);
    });

    it('hasil ditetapkan tanpa menunggu AK.03 asesi; AK.03 tetap dapat diisi setelahnya, dokumen lain terkunci', function () {
        [$p, $token, $tokenAsesi] = penutupSiap();
        ak02Selesai($this, $p, $token, $tokenAsesi, 'belum_kompeten');
        $base = "/api/muk/penilaian/{$p->id}/dokumen";

        // PATCH pertama menyimpan isian awal (header, dimensi, dst).
        foreach (['ak-05' => ['keterangan' => 'U.1 Unit 1'], 'ak-06' => ['prinsip' => ['keputusan' => ['validitas']]]] as $slug => $isian) {
            $this->withToken($token)->patchJson("{$base}/{$slug}", ['isian' => $isian])->assertOk();
            $this->withToken($token)->postJson("{$base}/{$slug}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
            $this->withToken($token)->postJson("{$base}/{$slug}/submit")->assertOk();
        }

        // AK.03 belum diisi asesi -> hasil tetap ditetapkan.
        expect(MukFixture::dokumen($p, JenisDokumenMuk::AK_03)->status)->toBe(DokumenPenilaian::STATUS_BELUM_DIISI)
            ->and($p->fresh()->status)->toBe(PenilaianMuk::STATUS_SELESAI_BELUM_KOMPETEN);
        // Dokumen asesor terkunci setelah selesai.
        $this->withToken($token)->patchJson("{$base}/ak-05", ['isian' => ['keterangan' => 'ubah']])->assertForbidden();
        akunBaru();

        // Asesi masih dapat mengisi & mengirim umpan balik.
        $this->withToken($tokenAsesi)->patchJson("{$base}/ak-03", ['isian' => ['komponen' => array_fill(0, 9, ['hasil' => 'ya'])]])->assertOk();
        $this->withToken($tokenAsesi)->postJson("{$base}/ak-03/submit")->assertOk()->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_SELESAI);
        expect($p->fresh()->status)->toBe(PenilaianMuk::STATUS_SELESAI_BELUM_KOMPETEN);
    });

    it('muk:sinkron-penyelesaian menetapkan hasil penilaian yang tertahan', function () {
        [$p, $token, $tokenAsesi] = penutupSiap();
        ak02Selesai($this, $p, $token, $tokenAsesi, 'kompeten');
        // Kondisi data lama: semua dokumen asesmen selesai tapi status masih berlangsung.
        foreach ([JenisDokumenMuk::AK_05, JenisDokumenMuk::AK_06] as $kode) {
            MukFixture::dokumen($p, $kode)->update(['status' => DokumenPenilaian::STATUS_SELESAI]);
        }
        expect($p->fresh()->status)->toBe(PenilaianMuk::STATUS_BERLANGSUNG);

        $this->artisan('muk:sinkron-penyelesaian', ['--dry-run' => true])->expectsOutputToContain("#{$p->id}")->assertSuccessful();
        expect($p->fresh()->status)->toBe(PenilaianMuk::STATUS_BERLANGSUNG);

        $this->artisan('muk:sinkron-penyelesaian')->assertSuccessful();
        expect($p->fresh()->status)->toBe(PenilaianMuk::STATUS_SELESAI_KOMPETEN)
            ->and($p->jadwalAsesmen->fresh()->status)->toBe('selesai');
        $this->artisan('muk:sinkron-penyelesaian')->expectsOutputToContain('0 penilaian')->assertSuccessful();
    });
});
