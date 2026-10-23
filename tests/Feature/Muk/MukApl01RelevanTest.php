<?php

use App\Models\Apl01BuktiRelevan;
use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\KriteriaUnjukKerja;
use App\Models\MapaTemplate;
use App\Models\SkemaSertifikasi;
use App\Models\User;
use App\Services\Muk\MapaTemplateService;
use App\Services\Muk\MetodeAsesmenService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MukFixture;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

/** Template VP skema berisi daftar bukti portofolio (CMS). */
function templateBuktiVp(int $skemaId, array $bukti): MapaTemplate
{
    $template = app(MapaTemplateService::class)->untuk(SkemaSertifikasi::findOrFail($skemaId), 'vp');
    $template->update(['instrumen' => [MapaTemplate::IA_08 => ['bukti' => $bukti]]]);

    return $template;
}

describe('FR.APL.01 lampiran bukti yang relevan', function () {
    it('butir template tampil & tidak bisa dihapus; asesi tambah, ubah link, unggah, hapus', function () {
        $asesi = Asesi::factory()->create();
        $p = MukFixture::penilaian(asesi: $asesi);
        $template = templateBuktiVp($p->pendaftaran->skema_sertifikasi_id, ['Laporan kegiatan', 'Surat Tugas']);
        $token = MukFixture::token($asesi);
        $url = "/api/muk/penilaian/{$p->id}/dokumen/apl-01";

        $res = $this->withToken($token)->getJson($url)->assertOk()
            ->assertJsonCount(2, 'data.bukti_relevan')
            ->assertJsonPath('data.bukti_relevan.0.nama', 'Laporan kegiatan')
            ->assertJsonPath('data.bukti_relevan.0.template', true)
            ->assertJsonPath('data.unggah.maks_relevan_tambahan', 20);
        // Idempoten: GET berulang tidak menggandakan butir.
        $this->withToken($token)->getJson($url)->assertJsonCount(2, 'data.bukti_relevan');
        [$laporan, $surat] = array_column($res->json('data.bukti_relevan'), 'id');

        // Butir template: tidak bisa dihapus / diganti nama, tapi bisa diisi link & berkas.
        $this->withToken($token)->deleteJson("{$url}/relevan/{$laporan}")->assertJsonValidationErrors('relevan');
        $this->withToken($token)->patchJson("{$url}/relevan/{$laporan}", ['nama' => 'Ganti'])->assertJsonValidationErrors('nama');
        $this->withToken($token)->patchJson("{$url}/relevan/{$laporan}", ['link' => 'ftp://x'])->assertJsonValidationErrors('link');
        $this->withToken($token)->patchJson("{$url}/relevan/{$laporan}", ['link' => 'https://drive.example/laporan'])->assertOk()
            ->assertJsonPath('data.bukti_relevan.0.link', 'https://drive.example/laporan');
        $this->withToken($token)->post("{$url}/relevan/{$surat}/file", ['file' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.bukti_relevan.1.file.nama', 'surat.pdf');
        $this->withToken($token)->get("{$url}/relevan/{$surat}/file")->assertOk();

        // Baris tambahan asesi: tambah, ganti nama, hapus.
        $this->withToken($token)->postJson("{$url}/relevan", ['nama' => ''])->assertJsonValidationErrors('nama');
        $baru = $this->withToken($token)->postJson("{$url}/relevan", ['nama' => 'Sertifikat pelatihan'])->assertOk()
            ->assertJsonCount(3, 'data.bukti_relevan')
            ->assertJsonPath('data.bukti_relevan.2.template', false)
            ->json('data.bukti_relevan.2.id');
        $this->withToken($token)->patchJson("{$url}/relevan/{$baru}", ['nama' => 'Sertifikat BNSP'])->assertOk()
            ->assertJsonPath('data.bukti_relevan.2.nama', 'Sertifikat BNSP');
        $this->withToken($token)->deleteJson("{$url}/relevan/{$baru}")->assertOk()->assertJsonCount(2, 'data.bukti_relevan');

        // Butir dihapus dari template: yang kosong dibuang, yang terisi menjadi baris asesi.
        $template->update(['instrumen' => [MapaTemplate::IA_08 => ['bukti' => ['Portofolio baru']]]]);
        $this->withToken($token)->getJson($url)->assertOk()
            ->assertJsonCount(3, 'data.bukti_relevan')
            ->assertJsonPath('data.bukti_relevan.0.nama', 'Portofolio baru')
            ->assertJsonPath('data.bukti_relevan.1.template', false);

        // Milik asesi lain / penilaian lain -> 404.
        $lain = MukFixture::penilaian();
        $this->withToken($token)->patchJson("/api/muk/penilaian/{$lain->id}/dokumen/apl-01/relevan/{$laporan}", ['link' => null])->assertNotFound();
    });

    it('tidak dapat diubah setelah dikirim; asesor tidak dapat mengubah', function () {
        $asesi = Asesi::factory()->create();
        $asesor = Asesor::factory()->create();
        $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);
        templateBuktiVp($p->pendaftaran->skema_sertifikasi_id, ['Laporan']);
        $url = "/api/muk/penilaian/{$p->id}/dokumen/apl-01";
        $id = $this->withToken(MukFixture::token($asesi))->getJson($url)->json('data.bukti_relevan.0.id');

        MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->update(['status' => DokumenPenilaian::STATUS_MENUNGGU_REVIEW]);
        $this->withToken(MukFixture::token($asesi))->patchJson("{$url}/relevan/{$id}", ['link' => 'https://a.id'])->assertStatus(403);
        app('auth')->forgetGuards();
        $this->withToken(MukFixture::token($asesor))->patchJson("{$url}/relevan/{$id}", ['link' => 'https://a.id'])->assertForbidden();
    });
});

describe('Bukti portofolio VP dari lampiran relevan', function () {
    it('IA.08 terisi dari lampiran berisi berkas/link; asesor dapat membuka berkas; IA.09 ikut', function () {
        $asesor = Asesor::factory()->create();
        $asesi = Asesi::factory()->create();
        $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);
        $apl01 = MukFixture::dokumen($p, JenisDokumenMuk::APL_01);
        $apl01->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_admin' => 'diterima']);
        Storage::disk('local')->put('apl01/x/laporan.pdf', '%PDF-1.4');
        $laporan = $apl01->buktiRelevan()->create(['kunci' => sha1('Laporan'), 'nama' => 'Laporan', 'path_file' => 'apl01/x/laporan.pdf', 'nama_file_asli' => 'laporan.pdf', 'mime_type' => 'application/pdf', 'urutan' => 0]);
        $apl01->buktiRelevan()->create(['kunci' => sha1('Kosong'), 'nama' => 'Kosong', 'urutan' => 1]);
        $sertifikat = $apl01->buktiRelevan()->create(['nama' => 'Sertifikat', 'link' => 'https://contoh.id/s', 'urutan' => 1001]);
        $lainId = Apl01BuktiRelevan::create(['dokumen_penilaian_id' => MukFixture::dokumen(MukFixture::penilaian(), JenisDokumenMuk::APL_01)->id, 'nama' => 'Milik orang lain', 'link' => 'https://x.id'])->id;

        MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_asesor' => 'dapat_dilanjutkan']);
        app(MetodeAsesmenService::class)->tetapkan($p, 'vp', $asesor);
        foreach ([JenisDokumenMuk::MAPA_01, JenisDokumenMuk::MAPA_02, JenisDokumenMuk::AK_07, JenisDokumenMuk::AK_01] as $kode) {
            MukFixture::dokumen($p, $kode)->update(['status' => DokumenPenilaian::STATUS_SELESAI]);
        }
        $token = MukFixture::token($asesor);
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-08";

        $this->withToken($token)->getJson($url)->assertOk()
            ->assertJsonCount(2, 'data.isian.portofolio')
            ->assertJsonPath('data.isian.portofolio.0', ['bukti' => 'Laporan', 'relevan_id' => $laporan->id, 'valid' => null, 'asli' => null, 'terkini' => null, 'memadai' => null])
            ->assertJsonPath('data.isian.portofolio.1.relevan_id', $sertifikat->id)
            ->assertJsonPath('data.lampiran.0.file.nama', 'laporan.pdf')
            ->assertJsonPath('data.lampiran.1.link', 'https://contoh.id/s');
        $this->withToken($token)->get("/api/muk/penilaian/{$p->id}/dokumen/apl-01/relevan/{$laporan->id}/file")->assertOk();

        // relevan_id milik penilaian lain dibuang.
        $this->withToken($token)->patchJson($url, ['isian' => ['portofolio' => [
            ['bukti' => 'Laporan', 'relevan_id' => $laporan->id, 'valid' => 'ya'],
            ['bukti' => 'Titipan', 'relevan_id' => $lainId],
        ]]])->assertOk()
            ->assertJsonPath('data.isian.portofolio.0.relevan_id', $laporan->id)
            ->assertJsonPath('data.isian.portofolio.1.relevan_id', null);

        $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/ia-09")->assertOk()
            ->assertJsonPath('data.isian.bukti', ['Laporan', 'Sertifikat']);
    });
});

it('IA.08 materi/substansi wawancara: awal dari template VP, asesor tambah/hapus, KUK luar skema dibuang', function () {
    $asesor = Asesor::factory()->create();
    $p = MukFixture::penilaian(asesor: $asesor);
    foreach ([JenisDokumenMuk::APL_01, JenisDokumenMuk::APL_02] as $kode) {
        MukFixture::dokumen($p, $kode)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_admin' => 'diterima', 'hasil_rekomendasi_asesor' => 'dapat_dilanjutkan']);
    }
    app(MetodeAsesmenService::class)->tetapkan($p, 'vp', $asesor);
    foreach ([JenisDokumenMuk::MAPA_01, JenisDokumenMuk::MAPA_02, JenisDokumenMuk::AK_07, JenisDokumenMuk::AK_01] as $kode) {
        MukFixture::dokumen($p, $kode)->update(['status' => DokumenPenilaian::STATUS_SELESAI]);
    }
    $kuk = KriteriaUnjukKerja::whereHas('elemenKompetensi.unitKompetensi', fn ($q) => $q->where('skema_sertifikasi_id', $p->pendaftaran->skema_sertifikasi_id))
        ->orderBy('id')->pluck('id')->all();
    $template = app(MapaTemplateService::class)->untuk(SkemaSertifikasi::findOrFail($p->pendaftaran->skema_sertifikasi_id), 'vp');
    $template->update(['instrumen' => [MapaTemplate::IA_08 => ['bukti' => [], 'materi' => [$kuk[2], $kuk[0]]]]]);
    $token = MukFixture::token($asesor);
    $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-08";

    $this->withToken($token)->getJson($url)->assertOk()->assertJsonPath('data.isian.materi', ["k{$kuk[0]}", "k{$kuk[2]}"]);
    $this->withToken($token)->patchJson($url, ['isian' => ['materi' => ["k{$kuk[1]}", 'k999999', "k{$kuk[2]}"]]])->assertOk()
        ->assertJsonPath('data.isian.materi', ["k{$kuk[1]}", "k{$kuk[2]}"]);
    // Setelah diatur asesor, perubahan template tidak memengaruhi dokumen; daftar kosong tetap kosong.
    $template->update(['instrumen' => [MapaTemplate::IA_08 => ['materi' => [$kuk[3]]]]]);
    $this->withToken($token)->patchJson($url, ['isian' => ['materi' => []]])->assertOk()->assertJsonPath('data.isian.materi', []);
});

it('CMS template VP: daftar bukti portofolio IA.08', function () {
    $admin = User::factory()->create();
    $skema = MukFixture::skema();

    $kuk = KriteriaUnjukKerja::whereHas('elemenKompetensi.unitKompetensi', fn ($q) => $q->where('skema_sertifikasi_id', $skema->id))
        ->orderBy('id')->first();
    $kukLain = KriteriaUnjukKerja::whereHas('elemenKompetensi.unitKompetensi', fn ($q) => $q->where('skema_sertifikasi_id', MukFixture::skema()->id))->first();

    $this->actingAs($admin)->get(route('skema-sertifikasi.mapa.edit', [$skema, 'vp']))
        ->assertOk()
        ->assertSee('FR.IA.08 · Bukti Portofolio')
        ->assertSee('FR.IA.08 · Materi/Substansi Wawancara/KUK')
        ->assertSee($kuk->deskripsi);

    $this->actingAs($admin)->put(route('skema-sertifikasi.mapa.update', [$skema, 'vp']), [
        'instrumen' => ['ia08' => ['bukti' => ['Laporan', '', 'Laporan', 'Surat Tugas'], 'materi' => [$kuk->id, '', $kuk->id, $kukLain->id]]],
    ])->assertSessionHasNoErrors();

    $vp = MapaTemplate::where('skema_sertifikasi_id', $skema->id)->where('metode', 'vp')->first();
    expect($vp->instrumenUntuk(MapaTemplate::IA_08)['bukti'])->toBe(['Laporan', 'Surat Tugas'])
        ->and($vp->instrumenUntuk(MapaTemplate::IA_08)['materi'])->toBe([$kuk->id])
        ->and($vp->instrumenUntuk(MapaTemplate::IA_09))->toBe([]);
});
