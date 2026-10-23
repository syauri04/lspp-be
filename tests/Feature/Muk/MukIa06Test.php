<?php

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\MapaTemplate;
use App\Models\PenilaianMuk;
use App\Models\SkemaSertifikasi;
use App\Models\User;
use App\Notifications\Muk\DokumenPersetujuanNotifikasi;
use App\Services\Muk\MapaTemplateService;
use App\Services\Muk\MetodeAsesmenService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MukFixture;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

/**
 * Penilaian (metode VP: IA.06 dipakai semua metode) dengan APL, MAPA, AK.07 & AK.01 selesai
 * + template Umum berisi 2 soal esai & kunci.
 *
 * @return array{0: PenilaianMuk, 1: string, 2: string, 3: MapaTemplate}
 */
function ia06Siap(): array
{
    $asesor = Asesor::factory()->create();
    $asesi = Asesi::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);

    MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_admin' => 'diterima']);
    MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_asesor' => 'dapat_dilanjutkan']);
    app(MetodeAsesmenService::class)->tetapkan($p, 'vp', $asesor);
    foreach ([JenisDokumenMuk::MAPA_01, JenisDokumenMuk::MAPA_02] as $kode) {
        MukFixture::dokumen($p, $kode)->update(['status' => DokumenPenilaian::STATUS_SELESAI]);
    }
    foreach ([JenisDokumenMuk::AK_07, JenisDokumenMuk::AK_01] as $kode) {
        MukFixture::dokumen($p, $kode)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'data' => ['tuk' => 'mandiri']]);
    }

    $template = app(MapaTemplateService::class)->untuk(SkemaSertifikasi::findOrFail($p->pendaftaran->skema_sertifikasi_id), MapaTemplate::UMUM);
    $template->update(['instrumen' => [MapaTemplate::IA_06 => [
        'waktu' => '30 Menit',
        'soal' => [
            ['pertanyaan' => 'Mengapa perencanaan penting?', 'kunci' => 'KUNCI-RAHASIA-1'],
            ['pertanyaan' => 'Sebutkan contoh risiko', 'kunci' => 'KUNCI-RAHASIA-2'],
        ],
    ]]]);
    Storage::disk('local')->put('mapa-template/ttd-ia06b.png', base64_decode(explode(',', pngDataUrl())[1]));
    $template->penandatangan()->create(['bagian' => 'penyusun_ia06b', 'peran' => 'validator', 'nama' => 'Validator Esai', 'path_ttd' => 'mapa-template/ttd-ia06b.png']);

    return [$p->fresh(), MukFixture::token($asesor), MukFixture::token($asesi), $template];
}

function gantiAkun(): void
{
    app('auth')->forgetGuards();
}

/** Asesor membuka IA.06A, asesi menjawab & mengirim. */
function ia06aDijawab($test, PenilaianMuk $p, string $token, string $tokenAsesi): void
{
    $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-06a";
    $test->withToken($token)->postJson("{$url}/buka")->assertOk();
    gantiAkun();
    $test->withToken($tokenAsesi)->patchJson($url, ['isian' => ['jawaban' => ['Agar teratur', 'Vendor terlambat']]])->assertOk();
    $test->withToken($tokenAsesi)->postJson("{$url}/submit")->assertOk();
    gantiAkun();
}

describe('FR.IA.06A', function () {
    it('asesor menyiapkan & membuka; asesi menjawab tanpa ttd; kunci tidak pernah terkirim', function () {
        [$p, $token, $tokenAsesi] = ia06Siap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-06a";

        $res = $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.persiapan.tuk', 'mandiri')
            ->assertJsonPath('data.persiapan.waktu', '30 Menit')
            ->assertJsonPath('data.pertanyaan', ['Mengapa perencanaan penting?', 'Sebutkan contoh risiko'])
            ->assertJsonPath('data.dokumen.dinilai.bisa_siapkan', true);
        expect($res->getContent())->not->toContain('KUNCI-RAHASIA');

        $this->withToken($token)->patchJson("{$url}/persiapan", ['persiapan' => ['waktu' => '45 Menit']])
            ->assertOk()
            ->assertJsonPath('data.persiapan.waktu', '45 Menit');
        gantiAkun();
        $this->withToken($tokenAsesi)->getJson($url)->assertForbidden()->assertJsonPath('message', 'Dokumen sedang disiapkan asesor.');
        gantiAkun();

        $this->withToken($token)->postJson("{$url}/buka")->assertOk()->assertJsonPath('data.persiapan.dibuka', true);
        // Template diubah setelah dibuka -> soal dokumen tetap.
        MapaTemplate::query()->where('metode', MapaTemplate::UMUM)->update(['instrumen' => null]);
        gantiAkun();

        $res = $this->withToken($tokenAsesi)->getJson($url)->assertOk()->assertJsonCount(2, 'data.pertanyaan');
        expect($res->getContent())->not->toContain('KUNCI-RAHASIA');
        $this->withToken($tokenAsesi)->patchJson($url, ['isian' => ['jawaban' => ['Agar teratur', null]]])->assertOk();
        $this->withToken($tokenAsesi)->postJson("{$url}/submit")->assertJsonValidationErrors('isian.jawaban.1');
        $this->withToken($tokenAsesi)->patchJson($url, ['isian' => ['jawaban' => ['Agar teratur', 'Vendor terlambat']]])->assertOk();
        $this->withToken($tokenAsesi)->postJson("{$url}/submit")
            ->assertOk()
            ->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_SELESAI);
        Notification::assertSentTo($p->jadwalAsesmen->asesor, DokumenPersetujuanNotifikasi::class);
    });

    it('tidak bisa dibuka bila soal belum diisi di CMS', function () {
        [$p, $token, , $template] = ia06Siap();
        $template->update(['instrumen' => null]);

        $this->withToken($token)->postJson("/api/muk/penilaian/{$p->id}/dokumen/ia-06a/buka")->assertJsonValidationErrors('persiapan.soal');
    });
});

describe('FR.IA.06B', function () {
    it('hanya asesor: kunci jawaban & penyusun; diselesaikan tanpa isian; dibekukan', function () {
        [$p, $token, $tokenAsesi] = ia06Siap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-06b";

        $this->withToken($tokenAsesi)->getJson($url)->assertForbidden()->assertJsonPath('message', 'Dokumen ini hanya untuk asesor.');
        gantiAkun();

        $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.soal.0.kunci', 'KUNCI-RAHASIA-1')
            ->assertJsonPath('data.penyusun.0.nama', 'Validator Esai')
            ->assertJsonPath('data.header.waktu', '30 Menit');
        $this->withToken($token)->patchJson($url, ['isian' => ['kunci' => 'ubah']])->assertJsonValidationErrors('isian');

        $pid = MapaTemplate::query()->where('metode', MapaTemplate::UMUM)->first()->penandatangan()->value('id');
        $this->withToken($token)->postJson("{$url}/submit")
            ->assertOk()
            ->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_SELESAI)
            ->assertJsonPath('data.penyusun.0.tanggal', now('Asia/Jakarta')->toDateString());

        MapaTemplate::query()->where('metode', MapaTemplate::UMUM)->update(['instrumen' => null]);
        Storage::disk('local')->delete('mapa-template/ttd-ia06b.png');
        $this->withToken($token)->getJson($url)->assertJsonPath('data.soal.1.kunci', 'KUNCI-RAHASIA-2');
        $this->withToken($token)->get("{$url}/penandatangan/{$pid}")->assertOk();
        gantiAkun();
        $this->withToken($tokenAsesi)->get("{$url}/penandatangan/{$pid}")->assertForbidden();
    });
});

describe('FR.IA.06C', function () {
    it('terbuka setelah IA.06A dikirim; asesor menilai jawaban asesi + ttd; asesi menyetujui', function () {
        [$p, $token, $tokenAsesi] = ia06Siap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-06c";

        $this->withToken($token)->getJson($url)->assertForbidden()->assertJsonPath('message', 'Menunggu asesi mengirim jawaban FR.IA.06A.');
        ia06aDijawab($this, $p, $token, $tokenAsesi);

        $res = $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.jawaban_asesi.1.jawaban', 'Vendor terlambat')
            ->assertJsonPath('data.header.tuk', 'mandiri')
            ->assertJsonPath('data.isian.pencapaian', [null, null]);
        expect($res->getContent())->not->toContain('KUNCI-RAHASIA')
            ->and($res->json('data.isian.umpan_balik'))->toContain('Aspek pengetahuan');

        $this->withToken($token)->patchJson($url, ['isian' => ['pencapaian' => ['ya'], 'umpan_balik' => '']])->assertOk();
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors(['isian.pencapaian.1', 'isian.umpan_balik']);

        $this->withToken($token)->patchJson($url, ['isian' => ['pencapaian' => ['ya', 'tidak'], 'umpan_balik' => 'Aspek pengetahuan tercapai sebagian.']])->assertOk();
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertOk()->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_MENUNGGU_PERSETUJUAN);
        gantiAkun();

        $this->withToken($tokenAsesi)->getJson($url)->assertOk()->assertJsonPath('data.isian.pencapaian', ['ya', 'tidak']);
        $this->withToken($tokenAsesi)->postJson("{$url}/setujui", ['tanda_tangan' => pngDataUrl()])
            ->assertOk()
            ->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_SELESAI);
    });
});

it('CMS: template Umum menyimpan soal, kunci, waktu & penyusun IA.06B; tidak menampilkan §2', function () {
    $admin = User::factory()->create();
    $skema = MukFixture::skema();

    $this->actingAs($admin)->get(route('skema-sertifikasi.mapa.edit', [$skema, 'umum']))
        ->assertOk()
        ->assertSee('FR.IA.06 · Soal Esai')
        ->assertSee('FR.IA.06B · Penyusun dan Validator')
        ->assertDontSee('2. Mempersiapkan Rencana Asesmen')
        ->assertDontSee('Konfirmasi dengan Orang yang Relevan');

    $this->actingAs($admin)->put(route('skema-sertifikasi.mapa.update', [$skema, 'umum']), [
        'instrumen' => ['ia06' => ['waktu' => '30 Menit', 'soal' => [
            'a' => ['pertanyaan' => 'Soal A?', 'kunci' => 'Kunci A'],
            'b' => ['pertanyaan' => '', 'kunci' => 'diabaikan'],
        ]]],
        'penandatangan' => ['penyusun_ia06b' => [['peran' => 'penyusun', 'nama' => 'Penyusun Esai']], 'penyusun_mapa01' => [['peran' => 'penyusun', 'nama' => 'X']]],
        'ada_penyusun_ia06b' => 1,
    ])->assertSessionHasNoErrors();

    $umum = MapaTemplate::where('skema_sertifikasi_id', $skema->id)->where('metode', MapaTemplate::UMUM)->first();
    expect($umum->instrumenUntuk(MapaTemplate::IA_06)['soal'])->toEqual([['pertanyaan' => 'Soal A?', 'kunci' => 'Kunci A']])
        ->and($umum->penandatangan()->where('bagian', 'penyusun_ia06b')->value('nama'))->toBe('Penyusun Esai')
        ->and($umum->penandatangan()->whereIn('bagian', ['konfirmasi', 'penyusun_mapa01'])->exists())->toBeFalse();

    // Template metode (mis. KT) tidak menampilkan/menyimpan IA.06.
    $this->actingAs($admin)->get(route('skema-sertifikasi.mapa.edit', [$skema, 'kt']))->assertOk()->assertDontSee('FR.IA.06 · Soal Esai');
});
