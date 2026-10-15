<?php

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\MapaTemplateRencana;
use App\Models\PenilaianMuk;
use App\Models\SkemaSertifikasi;
use App\Services\Muk\AksesDokumen;
use App\Services\Muk\MapaTemplateService;
use App\Services\Muk\MetodeAsesmenService;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MukFixture;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Penilaian dengan APL selesai, metode VP ditetapkan, dan template MAPA (opsional lengkap).
 *
 * @return array{0: PenilaianMuk, 1: string, 2: string, 3: int} [penilaian, token asesor, token asesi, id penandatangan bertanda tangan]
 */
function mapaSiap(bool $templateLengkap = true): array
{
    $asesor = Asesor::factory()->create();
    $asesi = Asesi::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);

    MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->update([
        'status' => DokumenPenilaian::STATUS_SELESAI,
        'hasil_rekomendasi_admin' => 'diterima',
        'data' => ['tujuan_asesmen' => 'rpl'],
    ]);
    MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->update([
        'status' => DokumenPenilaian::STATUS_SELESAI,
        'hasil_rekomendasi_asesor' => 'dapat_dilanjutkan',
    ]);
    app(MetodeAsesmenService::class)->tetapkan($p, 'vp', $asesor);

    $skema = SkemaSertifikasi::findOrFail($p->pendaftaran->skema_sertifikasi_id);
    $template = app(MapaTemplateService::class)->untuk($skema, 'vp');
    $units = $skema->unitKompetensis;
    foreach ($templateLengkap ? $units : $units->take(1) as $u) {
        MapaTemplateRencana::create([
            'mapa_template_id' => $template->id,
            'unit_kompetensi_id' => $u->id,
            'bukti' => 'Hasil Verifikasi Portofolio',
            'jenis_tl' => true,
            'verifikasi_portofolio' => 'CVP',
        ]);
    }

    $konfirmasi = $template->penandatangan()->where('bagian', 'konfirmasi')->first();
    Storage::disk('local')->put('mapa-template/ttd-uji.png', base64_decode(explode(',', pngDataUrl())[1]));
    $konfirmasi->update(['nama' => 'Dr. Manajer', 'path_ttd' => 'mapa-template/ttd-uji.png']);
    $template->penandatangan()->create(['bagian' => 'penyusun_mapa02', 'peran' => 'penyusun', 'nama' => 'Penyusun Dua', 'urutan' => 1]);

    return [$p->fresh(), MukFixture::token($asesor), MukFixture::token($asesi), $konfirmasi->id];
}

function isianMapa01Lengkap(): array
{
    return [
        'asesi' => ['3'],
        'lingkungan' => ['nyata'],
        'peluang' => ['tersedia'],
        'hubungan' => ['bukti' => ['checked' => true, 'emoji' => 'senang']],
        'modifikasi' => [
            'karakteristik' => ['ada' => false],
            'kontekstualisasi' => ['ada' => false],
            'saran' => ['ada' => true, 'catatan' => 'Saran asesor'],
            'penyesuaian' => ['ada' => false],
        ],
    ];
}

describe('FR.MAPA.01', function () {
    it('asesor melihat data otomatis & prefill (tujuan dari APL.01, LSP, manajer, peluang terintegrasi)', function () {
        [$p, $token] = mapaSiap();

        $res = $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/mapa-01")
            ->assertOk()
            ->assertJsonPath('data.dokumen.pengisi', 'asesor')
            ->assertJsonPath('data.dokumen.peninjau', null)
            ->assertJsonPath('data.dokumen.dapat_diubah', true)
            ->assertJsonPath('data.prefill', true)
            ->assertJsonPath('data.isian.tujuan', ['rpl'])
            ->assertJsonPath('data.isian.siapa', ['lsp'])
            ->assertJsonPath('data.isian.konfirmasi', ['manajer'])
            ->assertJsonPath('data.isian.modifikasi.peluang_terintegrasi.ada', true)
            ->assertJsonPath('data.template_lengkap', true)
            ->assertJsonPath('data.konfirmasi_orang.0.nama', 'Dr. Manajer')
            ->assertJsonPath('data.konfirmasi_orang.0.ada_ttd', true)
            ->assertJsonCount(2, 'data.rencana');

        expect($res->json('data.isian.modifikasi.peluang_terintegrasi.catatan'))->toContain('Unit 1')
            ->and(json_encode($res->json()))->not->toContain('_path')->not->toContain('mapa-template/');
    });

    it('asesi tidak bisa melihat/mengisi MAPA', function () {
        [$p, , $tokenAsesi] = mapaSiap();

        $this->withToken($tokenAsesi)->getJson("/api/muk/penilaian/{$p->id}/dokumen/mapa-01")->assertForbidden();
        $this->withToken($tokenAsesi)->patchJson("/api/muk/penilaian/{$p->id}/dokumen/mapa-01", ['isian' => isianMapa01Lengkap()])->assertForbidden();
    });

    it('validasi kunci & nilai isian', function () {
        [$p, $token] = mapaSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/mapa-01";

        $this->withToken($token)->patchJson($url, ['isian' => ['asesi' => ['9']]])->assertJsonValidationErrors('isian.asesi.0');
        $this->withToken($token)->patchJson($url, ['isian' => ['hubungan' => ['bukti' => ['emoji' => 'marah']]]])->assertJsonValidationErrors('isian.hubungan.bukti.emoji');
        $this->withToken($token)->patchJson($url, ['isian' => ['asing' => 1]])->assertJsonValidationErrors('isian');
    });

    it('selesaikan menolak isian belum lengkap, lalu selesai tanpa ttd & membekukan template', function () {
        [$p, $token, , $pid] = mapaSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/mapa-01";

        $this->withToken($token)->postJson("{$url}/submit")->assertStatus(409);
        $this->withToken($token)->patchJson($url, ['isian' => ['peluang' => ['terbatas']]])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")
            ->assertJsonValidationErrors(['isian.asesi', 'isian.lingkungan', 'isian.modifikasi.karakteristik']);

        $this->withToken($token)->patchJson($url, ['isian' => isianMapa01Lengkap()])
            ->assertOk()
            ->assertJsonPath('data.prefill', false)
            ->assertJsonPath('data.isian.tujuan', ['rpl']);
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertNotFound();

        $this->withToken($token)->postJson("{$url}/submit")
            ->assertOk()
            ->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_SELESAI)
            ->assertJsonPath('data.dokumen.dapat_diubah', false);

        $d = MukFixture::dokumen($p, JenisDokumenMuk::MAPA_01);
        $salinan = $d->snapshot['penandatangan']['konfirmasi'][0]['_path'];
        expect($salinan)->toBe("signatures/{$p->id}/mapa-01/penandatangan-{$pid}.png");
        Storage::disk('local')->assertExists($salinan);

        // Template diubah setelah selesai -> dokumen tetap memakai snapshot.
        MapaTemplateRencana::query()->update(['bukti' => 'Diubah admin']);
        Storage::disk('local')->delete('mapa-template/ttd-uji.png');
        $this->withToken($token)->getJson($url)->assertJsonPath('data.rencana.0.bukti', 'Hasil Verifikasi Portofolio');
        $this->withToken($token)->get("{$url}/penandatangan/{$pid}")->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->withToken($token)->patchJson($url, ['isian' => isianMapa01Lengkap()])->assertForbidden();
    });

    it('tidak bisa diselesaikan bila template rencana belum lengkap', function () {
        [$p, $token] = mapaSiap(templateLengkap: false);
        $url = "/api/muk/penilaian/{$p->id}/dokumen/mapa-01";

        $this->withToken($token)->patchJson($url, ['isian' => isianMapa01Lengkap()])->assertOk()->assertJsonPath('data.template_lengkap', false);
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors('rencana');
    });
});

describe('FR.MAPA.02', function () {
    it('isi potensi, selesaikan, dan AK/IA terbuka setelah kedua MAPA selesai', function () {
        [$p, $token] = mapaSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/mapa-02";

        $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonCount(12, 'data.instrumen')
            ->assertJsonPath('data.instrumen.3.nomor', '4.a')
            ->assertJsonPath('data.penyusun.0.nama', 'Penyusun Dua');

        $this->withToken($token)->postJson("{$url}/submit")->assertStatus(409);
        $this->withToken($token)->patchJson($url, ['isian' => ['potensi' => ['ia01' => [1]]]])->assertOk();
        $this->withToken($token)->patchJson($url, ['isian' => ['potensi' => ['ia01' => []]]])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors('isian.potensi');
        $this->withToken($token)->patchJson($url, ['isian' => ['potensi' => ['ia01' => [6]]]])->assertJsonValidationErrors('isian.potensi.ia01.0');

        $this->withToken($token)->patchJson($url, ['isian' => ['potensi' => ['ia08' => [3, 1, 1], 'ia09' => [1, 3]]]])
            ->assertOk()
            ->assertJsonPath('data.isian.potensi.ia08', [1, 3])
            ->assertJsonPath('data.isian.potensi.ia09', [1, 3])
            ->assertJsonPath('data.isian.potensi.ia01', []);
        $this->withToken($token)->postJson("{$url}/submit")->assertOk()->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_SELESAI);

        $semua = fn () => $p->dokumenPenilaian()->with('jenisDokumen')->get();
        expect(AksesDokumen::mapaSelesai($semua()))->toBeFalse();

        MukFixture::dokumen($p, JenisDokumenMuk::MAPA_01)->update(['status' => DokumenPenilaian::STATUS_SELESAI]);
        expect(AksesDokumen::mapaSelesai($semua()))->toBeTrue();
    });

    it('asesor lain mendapat 404 untuk dokumen & gambar ttd', function () {
        [$p, , , $pid] = mapaSiap();
        $lain = MukFixture::token(Asesor::factory()->create());

        $this->withToken($lain)->getJson("/api/muk/penilaian/{$p->id}/dokumen/mapa-02")->assertNotFound();
        $this->withToken($lain)->get("/api/muk/penilaian/{$p->id}/dokumen/mapa-01/penandatangan/{$pid}")->assertNotFound();
    });
});
