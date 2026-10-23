<?php

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\KriteriaUnjukKerja;
use App\Models\MapaTemplate;
use App\Models\PenilaianMuk;
use App\Models\SkemaSertifikasi;
use App\Models\UnitKompetensi;
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
 * Penilaian VP: APL, MAPA, AK.07 & AK.01 selesai (IA terbuka) + template VP (pertanyaan IA.09, penyusun).
 *
 * @return array{0: PenilaianMuk, 1: string, 2: string, 3: MapaTemplate}
 */
function vpSiap(): array
{
    $asesor = Asesor::factory()->create();
    $asesi = Asesi::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);

    MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->update([
        'status' => DokumenPenilaian::STATUS_SELESAI,
        'hasil_rekomendasi_admin' => 'diterima',
        'data' => ['pekerjaan' => ['nama_institusi' => 'PT Acara Jaya', 'alamat_kantor' => 'Jl. Event 1', 'telp' => '0211234']],
    ]);
    MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_asesor' => 'dapat_dilanjutkan']);
    app(MetodeAsesmenService::class)->tetapkan($p, 'vp', $asesor);
    foreach ([JenisDokumenMuk::MAPA_01, JenisDokumenMuk::MAPA_02, JenisDokumenMuk::AK_07, JenisDokumenMuk::AK_01] as $kode) {
        MukFixture::dokumen($p, $kode)->update(['status' => DokumenPenilaian::STATUS_SELESAI]);
    }
    MukFixture::dokumen($p, JenisDokumenMuk::AK_01)->update(['data' => ['tuk' => 'tempat_kerja']]);

    $template = app(MapaTemplateService::class)->untuk(SkemaSertifikasi::findOrFail($p->pendaftaran->skema_sertifikasi_id), 'vp');
    $template->update(['instrumen' => [MapaTemplate::IA_09 => ['pertanyaan' => ['Ceritakan pengalaman registrasi tamu?']]]]);
    Storage::disk('local')->put('mapa-template/ttd-ia09.png', base64_decode(explode(',', pngDataUrl())[1]));
    $template->penandatangan()->create(['bagian' => 'penyusun_ia09', 'peran' => 'penyusun', 'nama' => 'Penyusun Wawancara', 'path_ttd' => 'mapa-template/ttd-ia09.png']);

    return [$p->fresh(), MukFixture::token($asesor), MukFixture::token($asesi), $template];
}

/** Ttd asesor -> kirim -> (opsional) asesi setujui. */
function kirimVp($test, string $url, string $token, ?string $tokenAsesi): void
{
    $test->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
    $test->withToken($token)->postJson("{$url}/submit")->assertOk();
    if ($tokenAsesi) {
        app('auth')->forgetGuards();
        $test->withToken($tokenAsesi)->postJson("{$url}/setujui", ['tanda_tangan' => pngDataUrl()])
            ->assertOk()
            ->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_SELESAI);
        app('auth')->forgetGuards();
    }
}

describe('FR.IA.08', function () {
    it('KUK skema untuk klarifikasi; verifikasi aturan bukti; alur persetujuan', function () {
        [$p, $token, $tokenAsesi] = vpSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-08";
        $kuk = KriteriaUnjukKerja::whereHas('elemenKompetensi.unitKompetensi', fn ($q) => $q->where('skema_sertifikasi_id', $p->pendaftaran->skema_sertifikasi_id))
            ->orderBy('id')->first();

        $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.isian.tuk', 'tempat_kerja')
            ->assertJsonCount(4, 'data.kuk')
            ->assertJsonPath('data.kuk.0.kunci', 'k'.$kuk->id)
            ->assertJsonPath('data.kuk.0.unit_no', 1)
            ->assertJsonPath('data.kuk.0.unit_kunci', 'u'.$kuk->elemenKompetensi->unit_kompetensi_id);
        $unit = 'u'.$kuk->elemenKompetensi->unit_kompetensi_id;

        $this->withToken($token)->patchJson($url, ['isian' => [
            'portofolio' => [['bukti' => 'Surat Tugas', 'valid' => 'ya'], ['bukti' => '']],
            // Centang per unit; kunci tak dikenal dibuang; kunci KUK lama dipetakan ke unitnya.
            'klarifikasi' => [$unit, 'u999999', 'k'.$kuk->id],
            'rekomendasi' => 'kompeten',
        ]])->assertOk()
            ->assertJsonCount(1, 'data.isian.portofolio')
            ->assertJsonPath('data.isian.klarifikasi', [$unit]);
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors('isian.portofolio.0');

        $this->withToken($token)->patchJson($url, ['isian' => [
            'portofolio' => [['bukti' => 'Surat Tugas', 'valid' => 'ya', 'asli' => 'ya', 'terkini' => 'ya', 'memadai' => 'tidak']],
        ]])->assertOk();
        kirimVp($this, $url, $token, $tokenAsesi);
    });
});

describe('FR.IA.08 rekomendasi lanjutan', function () {
    it('observasi langsung: lingkup unit+KUK, dokumen TPD dibuat saat IA.08 selesai & hanya memuat lingkup', function () {
        [$p, $token, $tokenAsesi] = vpSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-08";
        $skemaId = $p->pendaftaran->skema_sertifikasi_id;
        $units = UnitKompetensi::where('skema_sertifikasi_id', $skemaId)->with('elemenKompetensi.kriteriaUnjukKerja')->orderBy('id')->get();
        [$u1, $u2] = [$units[0], $units[1]];
        $kukU1 = $u1->elemenKompetensi->flatMap->kriteriaUnjukKerja->pluck('id')->all();
        $kukU2 = $u2->elemenKompetensi->flatMap->kriteriaUnjukKerja->pluck('id')->all();
        // Template TPD dipakai IA.02 lanjutan (bukan template VP).
        app(MapaTemplateService::class)->untuk(SkemaSertifikasi::findOrFail($skemaId), 'tpd')
            ->update(['instrumen' => [MapaTemplate::IA_02 => ['skenario' => 'Skenario observasi TPD', 'waktu' => '60 menit']]]);

        $this->withToken($token)->patchJson($url, ['isian' => [
            'portofolio' => [['bukti' => 'Laporan', 'valid' => 'ya', 'asli' => 'ya', 'terkini' => 'tidak', 'memadai' => 'tidak']],
            'rekomendasi' => 'lanjutan',
        ]])->assertOk();
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors(['isian.lanjutan.jenis', 'isian.lanjutan.unit']);
        $this->withToken($token)->patchJson($url, ['isian' => ['lanjutan' => ['jenis' => 'teleportasi']]])->assertJsonValidationErrors('isian.lanjutan.jenis');

        // KUK unit yang tidak dipilih & unit di luar skema dibuang.
        $this->withToken($token)->patchJson($url, ['isian' => ['lanjutan' => [
            'jenis' => 'observasi',
            'unit' => ["u{$u1->id}", 'u999999'],
            'kuk' => ["k{$kukU1[0]}", "k{$kukU2[0]}"],
        ]]])->assertOk()
            ->assertJsonPath('data.isian.lanjutan', ['jenis' => 'observasi', 'unit' => ["u{$u1->id}"], 'kuk' => ["k{$kukU1[0]}"]]);
        expect($p->dokumenPenilaian()->kode(JenisDokumenMuk::IA_01)->exists())->toBeFalse();
        kirimVp($this, $url, $token, $tokenAsesi);

        // Dokumen TPD dibuat (bukan KT), penilaian belum selesai.
        expect($p->dokumenPenilaian()->kode(JenisDokumenMuk::IA_01)->value('status'))->toBe(DokumenPenilaian::STATUS_BELUM_DIISI)
            ->and($p->dokumenPenilaian()->kode(JenisDokumenMuk::IA_02)->exists())->toBeTrue()
            ->and($p->dokumenPenilaian()->kode(JenisDokumenMuk::IA_03)->exists())->toBeTrue()
            ->and($p->dokumenPenilaian()->kode(JenisDokumenMuk::IA_04A)->exists())->toBeFalse();

        $ia01 = $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/ia-01")->assertOk();
        expect($ia01->json('data.struktur'))->toHaveCount(1)
            ->and($ia01->json('data.struktur.0.id'))->toBe($u1->id)
            ->and(collect($ia01->json('data.struktur.0.elemen'))->flatMap(fn ($e) => array_column($e['kuk'], 'id'))->all())->toBe([$kukU1[0]]);
        $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/ia-02")->assertOk()
            ->assertJsonCount(1, 'data.unit_kompetensi')
            ->assertJsonPath('data.instrumen.skenario', 'Skenario observasi TPD');

        // AK.02: indikasi IA.08 & bukti observasi hanya pada unit lingkup.
        $p->dokumenPenilaian()->with('jenisDokumen')->get()
            ->filter(fn ($d) => $d->jenisDokumen->kategori === 'IA')
            ->each(fn ($d) => $d->update(['status' => DokumenPenilaian::STATUS_SELESAI]));
        $ak02 = $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/ak-02")->assertOk();
        expect(collect($ak02->json('data.indikasi'))->pluck('dokumen')->all())->toContain('FR.IA.08')
            ->and($ak02->json("data.isian.bukti.u{$u1->id}"))->toContain('observasi')
            ->and($ak02->json("data.isian.bukti.u{$u2->id}"))->not->toContain('observasi');
    });
});

describe('FR.IA.09', function () {
    it('pertanyaan dari template VP + tambahan; bukti dari lampiran relevan APL.01; penyusun dibekukan', function () {
        [$p, $token, $tokenAsesi, $template] = vpSiap();
        $apl01 = MukFixture::dokumen($p, JenisDokumenMuk::APL_01);
        $apl01->buktiRelevan()->create(['kunci' => sha1('Surat Tugas'), 'nama' => 'Surat Tugas', 'link' => 'https://contoh.id/st']);
        $apl01->buktiRelevan()->create(['nama' => 'Kosong tanpa berkas']);
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-09";

        $res = $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.isian.bukti', ['Surat Tugas'])
            ->assertJsonPath('data.isian.pertanyaan', ['Ceritakan pengalaman registrasi tamu?'])
            ->assertJsonPath('data.penyusun.0.nama', 'Penyusun Wawancara');
        expect($res->getContent())->not->toContain('_path');

        $this->withToken($token)->patchJson($url, ['isian' => ['pertanyaan' => ['palsu']]])->assertJsonValidationErrors('isian');
        $this->withToken($token)->patchJson($url, ['isian' => [
            'jawaban' => [['kesimpulan' => 'Mampu menangani', 'pencapaian' => 'ya']],
            'tambahan' => [['teks' => 'Pertanyaan tambahan?', 'pencapaian' => 'ya']],
        ]])->assertOk();
        $template->update(['instrumen' => [MapaTemplate::IA_09 => ['pertanyaan' => ['Lain?']]]]);
        $this->withToken($token)->getJson($url)->assertJsonPath('data.isian.pertanyaan.0', 'Ceritakan pengalaman registrasi tamu?');

        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors('isian.tambahan.0');
        $this->withToken($token)->patchJson($url, ['isian' => ['tambahan' => [['teks' => 'Pertanyaan tambahan?', 'kesimpulan' => 'Baik', 'pencapaian' => 'ya']]]])->assertOk();
        kirimVp($this, $url, $token, $tokenAsesi);

        $this->withToken($token)->getJson($url)->assertJsonPath('data.penyusun.0.tanggal', now('Asia/Jakarta')->toDateString());
    });
});

describe('FR.IA.10', function () {
    it('isian awal dari data pekerjaan APL.01; hanya ttd asesor -> selesai; asesi baca setelahnya', function () {
        [$p, $token, $tokenAsesi] = vpSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-10";

        $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.isian.pengawas.tempat_kerja', 'PT Acara Jaya')
            ->assertJsonPath('data.isian.pengawas.telepon', '0211234')
            ->assertJsonCount(6, 'data.pertanyaan')
            ->assertJsonPath('data.dokumen.penyetuju', null);
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors(['isian.pengawas', 'isian.jawaban.0', 'isian.keyakinan']);

        $this->withToken($token)->patchJson($url, ['isian' => [
            'pengawas' => ['nama' => 'Budi (Supervisor)'],
            'jawaban' => array_fill(0, 6, 'ya'),
            'keyakinan' => 'Ya, konsisten sesuai standar.',
        ]])->assertOk()->assertJsonPath('data.isian.pengawas.alamat', 'Jl. Event 1');
        kirimVp($this, $url, $token, null);
        expect(MukFixture::dokumen($p, JenisDokumenMuk::IA_10)->status)->toBe(DokumenPenilaian::STATUS_SELESAI);

        app('auth')->forgetGuards();
        $this->withToken($tokenAsesi)->getJson($url)->assertOk()->assertJsonPath('data.isian.pengawas.nama', 'Budi (Supervisor)');
    });
});

it('CMS template VP: pertanyaan IA.09 & penyusun IA.09', function () {
    $admin = User::factory()->create();
    $skema = MukFixture::skema();

    $this->actingAs($admin)->get(route('skema-sertifikasi.mapa.edit', [$skema, 'vp']))
        ->assertOk()
        ->assertSee('FR.IA.09 · Pertanyaan Wawancara')
        ->assertSee('FR.IA.09 · Penyusun dan Validator')
        ->assertDontSee('FR.IA.03 · Pertanyaan');

    $this->actingAs($admin)->put(route('skema-sertifikasi.mapa.update', [$skema, 'vp']), [
        'instrumen' => ['ia09' => ['pertanyaan' => ['Q1?', '', 'Q2?']]],
        'penandatangan' => ['penyusun_ia09' => [['peran' => 'validator', 'nama' => 'Validator VP']]],
        'ada_penyusun_ia09' => 1,
    ])->assertSessionHasNoErrors();

    $vp = MapaTemplate::where('skema_sertifikasi_id', $skema->id)->where('metode', 'vp')->first();
    expect($vp->instrumenUntuk(MapaTemplate::IA_09)['pertanyaan'])->toBe(['Q1?', 'Q2?'])
        ->and($vp->penandatangan()->where('bagian', 'penyusun_ia09')->value('nama'))->toBe('Validator VP');
});
