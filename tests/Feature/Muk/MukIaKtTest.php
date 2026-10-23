<?php

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\MapaTemplate;
use App\Models\PenilaianMuk;
use App\Models\SkemaSertifikasi;
use App\Models\User;
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
 * Penilaian KT: APL, MAPA, AK.07 & AK.01 selesai (IA terbuka) + template KT (IA.04A/B, penyusun).
 *
 * @return array{0: PenilaianMuk, 1: string, 2: string, 3: MapaTemplate}
 */
function ktSiap(): array
{
    $asesor = Asesor::factory()->create();
    $asesi = Asesi::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);

    MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_admin' => 'diterima']);
    MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_asesor' => 'dapat_dilanjutkan']);
    app(MetodeAsesmenService::class)->tetapkan($p, 'kt', $asesor);
    foreach ([JenisDokumenMuk::MAPA_01, JenisDokumenMuk::MAPA_02, JenisDokumenMuk::AK_07, JenisDokumenMuk::AK_01] as $kode) {
        MukFixture::dokumen($p, $kode)->update(['status' => DokumenPenilaian::STATUS_SELESAI]);
    }
    MukFixture::dokumen($p, JenisDokumenMuk::AK_01)->update(['data' => ['tuk' => 'sewaktu']]);

    $template = app(MapaTemplateService::class)->untuk(SkemaSertifikasi::findOrFail($p->pendaftaran->skema_sertifikasi_id), 'kt');
    $template->update(['instrumen' => [
        MapaTemplate::IA_04A => ['hal_disiapkan' => 'Siapkan paparan MICE', 'hal_didemonstrasikan' => 'Presentasikan paparan', 'waktu_pengerjaan' => '60 menit', 'total_waktu' => '120 menit'],
        MapaTemplate::IA_04B => ['aspek' => [
            ['lingkup' => 'Komunikasi', 'pertanyaan' => 'Jelaskan koordinasi?', 'kesesuaian' => '(TLCM5)'],
            ['lingkup' => 'Risiko', 'pertanyaan' => 'Jelaskan mitigasi?', 'kesesuaian' => '(TMS)'],
        ]],
    ]]);
    Storage::disk('local')->put('mapa-template/ttd-ia04.png', base64_decode(explode(',', pngDataUrl())[1]));
    $template->penandatangan()->create(['bagian' => 'penyusun_ia04a', 'peran' => 'penyusun', 'nama' => 'Penyusun DIT', 'path_ttd' => 'mapa-template/ttd-ia04.png']);
    $template->penandatangan()->create(['bagian' => 'penyusun_ia04b', 'peran' => 'validator', 'nama' => 'Validator Proyek']);

    return [$p->fresh(), MukFixture::token($asesor), MukFixture::token($asesi), $template];
}

function kirimKt($test, string $url, string $token, string $tokenAsesi): void
{
    $test->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
    $test->withToken($token)->postJson("{$url}/submit")->assertOk()->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_MENUNGGU_PERSETUJUAN);
    app('auth')->forgetGuards();
    $test->withToken($tokenAsesi)->postJson("{$url}/setujui", ['tanda_tangan' => pngDataUrl()])
        ->assertOk()
        ->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_SELESAI);
    app('auth')->forgetGuards();
}

describe('FR.IA.04A', function () {
    it('instruksi & penyusun dari template KT (tidak bisa diubah); dibekukan saat dikirim', function () {
        [$p, $token, $tokenAsesi, $template] = ktSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-04a";

        $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.isian.tuk', 'sewaktu')
            ->assertJsonPath('data.instrumen.hal_disiapkan', 'Siapkan paparan MICE')
            ->assertJsonPath('data.penyusun.0.nama', 'Penyusun DIT')
            ->assertJsonCount(2, 'data.unit_kompetensi');
        $this->withToken($token)->patchJson($url, ['isian' => ['hal_disiapkan' => 'ubah']])->assertJsonValidationErrors('isian');
        $this->withToken($token)->patchJson($url, ['isian' => ['umpan_balik' => 'Paparan baik', 'supervisor' => 'Pak Andi']])->assertOk();
        kirimKt($this, $url, $token, $tokenAsesi);

        $template->update(['instrumen' => null]);
        $this->withToken($token)->getJson($url)
            ->assertJsonPath('data.instrumen.hal_didemonstrasikan', 'Presentasikan paparan')
            ->assertJsonPath('data.isian.supervisor', 'Pak Andi')
            ->assertJsonPath('data.instrumen.waktu_pengerjaan', '60 menit')
            ->assertJsonPath('data.instrumen.waktu_tanya_jawab', null)
            ->assertJsonPath('data.instrumen.total_waktu', '120 menit')
            ->assertJsonPath('data.penyusun.0.tanggal', now('Asia/Jakarta')->toDateString());
    });

    it('tidak bisa dikirim bila instruksi belum diisi di CMS', function () {
        [$p, $token, , $template] = ktSiap();
        $template->update(['instrumen' => null]);
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-04a";

        $this->withToken($token)->patchJson($url, ['isian' => ['tuk' => 'mandiri']])->assertOk();
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors('instrumen');
    });
});

describe('FR.IA.04B', function () {
    it('aspek dari template + tambahan; rekomendasi konsisten; alur persetujuan; indikasi di AK.02', function () {
        [$p, $token, $tokenAsesi, $template] = ktSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-04b";

        $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonCount(2, 'data.isian.aspek')
            ->assertJsonPath('data.isian.aspek.0.kesesuaian', '(TLCM5)')
            ->assertJsonPath('data.isian.jawaban.0.kesesuaian', '(TLCM5)')
            ->assertJsonPath('data.penyusun.0.nama', 'Validator Proyek');
        $this->withToken($token)->patchJson($url, ['isian' => ['aspek' => []]])->assertJsonValidationErrors('isian');

        $this->withToken($token)->patchJson($url, ['isian' => [
            'jawaban' => [['tanggapan' => 'Baik', 'pencapaian' => 'ya', 'kesesuaian' => '(TLCM5, Elemen 2)'], ['pencapaian' => 'tidak']],
            'tambahan' => [['lingkup' => 'Anggaran', 'pertanyaan' => 'Jelaskan anggaran?', 'pencapaian' => 'ya']],
            'rekomendasi' => 'kompeten',
        ]])->assertOk()
            ->assertJsonCount(1, 'data.isian.tambahan')
            ->assertJsonPath('data.isian.jawaban.0.kesesuaian', '(TLCM5, Elemen 2)')
            ->assertJsonPath('data.isian.jawaban.1.kesesuaian', '(TMS)')
            ->assertJsonPath('data.isian.aspek.0.kesesuaian', '(TLCM5)');
        $template->update(['instrumen' => [MapaTemplate::IA_04B => ['aspek' => [['pertanyaan' => 'Lain?']]]]]);
        $this->withToken($token)->getJson($url)->assertJsonPath('data.isian.aspek.0.pertanyaan', 'Jelaskan koordinasi?');

        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors('isian.rekomendasi');

        $this->withToken($token)->patchJson($url, ['isian' => ['rekomendasi' => 'belum_kompeten', 'rekomendasi_teks' => 'Perlu pendalaman risiko']])->assertOk();
        kirimKt($this, $url, $token, $tokenAsesi);

        // Hasil belum tercapai IA.04B tampil sebagai indikasi di AK.02.
        $p->dokumenPenilaian()->with('jenisDokumen')->get()
            ->filter(fn ($d) => $d->jenisDokumen->kategori === 'IA')
            ->each(fn ($d) => $d->update(['status' => DokumenPenilaian::STATUS_SELESAI]));
        $indikasi = collect($this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/ak-02")->assertOk()->json('data.indikasi'));
        expect($indikasi->where('dokumen', 'FR.IA.04B')->pluck('keterangan')->all())
            ->toContain('1 aspek penilaian belum tercapai', 'Direkomendasikan Belum Kompeten');
        expect($this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/ak-02")->json("data.isian.bukti.u{$p->pendaftaran->skemaSertifikasi->unitKompetensis->first()->id}"))
            ->toContain('proyek_kerja');
    });
});

it('CMS template KT: instruksi IA.04A, aspek IA.04B & penyusun', function () {
    $admin = User::factory()->create();
    $skema = MukFixture::skema();

    $this->actingAs($admin)->get(route('skema-sertifikasi.mapa.edit', [$skema, 'kt']))
        ->assertOk()
        ->assertSee('FR.IA.04A · Penjelasan Proyek Singkat')
        ->assertSee('FR.IA.04B · Aspek Penilaian')
        ->assertSee('FR.IA.04A · Penyusun dan Validator')
        ->assertSee('FR.IA.04B · Penyusun dan Validator');

    $this->actingAs($admin)->put(route('skema-sertifikasi.mapa.update', [$skema, 'kt']), [
        'instrumen' => [
            'ia04a' => ['hal_disiapkan' => 'Siapkan', 'hal_didemonstrasikan' => 'Presentasikan', 'waktu_pengerjaan' => '60 menit', 'waktu_tanya_jawab' => '30 menit', 'total_waktu' => '120 menit'],
            'ia04b' => ['aspek' => [
                'a' => ['lingkup' => 'L1', 'pertanyaan' => 'P1?', 'kesesuaian' => 'K1'],
                'b' => ['lingkup' => 'tanpa pertanyaan'],
            ]],
        ],
        'penandatangan' => ['penyusun_ia04a' => [['peran' => 'penyusun', 'nama' => 'Penyusun A']]],
        'ada_penyusun_ia04a' => 1,
    ])->assertSessionHasNoErrors();

    $kt = MapaTemplate::where('skema_sertifikasi_id', $skema->id)->where('metode', 'kt')->first();
    expect($kt->instrumenUntuk(MapaTemplate::IA_04A))->toEqual(['hal_disiapkan' => 'Siapkan', 'hal_didemonstrasikan' => 'Presentasikan', 'waktu_pengerjaan' => '60 menit', 'waktu_tanya_jawab' => '30 menit', 'total_waktu' => '120 menit'])
        ->and($kt->instrumenUntuk(MapaTemplate::IA_04B)['aspek'])->toEqual([['lingkup' => 'L1', 'pertanyaan' => 'P1?', 'kesesuaian' => 'K1']])
        ->and($kt->penandatangan()->where('bagian', 'penyusun_ia04a')->value('nama'))->toBe('Penyusun A');
});
