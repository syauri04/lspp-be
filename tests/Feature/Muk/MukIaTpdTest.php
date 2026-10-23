<?php

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\ElemenKompetensi;
use App\Models\JenisDokumenMuk;
use App\Models\MapaTemplate;
use App\Models\PenilaianMuk;
use App\Models\SkemaSertifikasi;
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
 * Penilaian TPD dengan APL, MAPA, AK.07 & AK.01 selesai (dokumen IA terbuka) + template TPD.
 *
 * @return array{0: PenilaianMuk, 1: string, 2: string, 3: MapaTemplate} [penilaian, token asesor, token asesi, template]
 */
function iaSiap(): array
{
    $asesor = Asesor::factory()->create();
    $asesi = Asesi::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);

    MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_admin' => 'diterima']);
    MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_asesor' => 'dapat_dilanjutkan']);
    app(MetodeAsesmenService::class)->tetapkan($p, 'tpd', $asesor);
    foreach ([JenisDokumenMuk::MAPA_01, JenisDokumenMuk::MAPA_02] as $kode) {
        MukFixture::dokumen($p, $kode)->update(['status' => DokumenPenilaian::STATUS_SELESAI]);
    }
    foreach ([JenisDokumenMuk::AK_07, JenisDokumenMuk::AK_01] as $kode) {
        MukFixture::dokumen($p, $kode)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'data' => ['tuk' => 'tempat_kerja']]);
    }

    $template = app(MapaTemplateService::class)->untuk(SkemaSertifikasi::findOrFail($p->pendaftaran->skema_sertifikasi_id), 'tpd');
    $template->update(['instrumen' => [
        MapaTemplate::IA_02 => ['skenario' => "Skenario uji\n1. Unit 1", 'waktu' => '20 Menit'],
        MapaTemplate::IA_03 => ['pertanyaan' => ['Pertanyaan satu?', 'Pertanyaan dua?']],
    ]]);
    Storage::disk('local')->put('mapa-template/ttd-ia02.png', base64_decode(explode(',', pngDataUrl())[1]));
    $template->penandatangan()->create(['bagian' => 'penyusun_ia02', 'peran' => 'penyusun', 'nama' => 'Penyusun IA', 'path_ttd' => 'mapa-template/ttd-ia02.png']);

    return [$p->fresh(), MukFixture::token($asesor), MukFixture::token($asesi), $template];
}

/** Tanda tangan asesor, kirim, lalu asesi menyetujui -> selesai. */
function kirimDanSetujui($test, string $url, string $token, string $tokenAsesi): void
{
    $test->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
    $test->withToken($token)->postJson("{$url}/submit")->assertOk()->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_MENUNGGU_PERSETUJUAN);
    app('auth')->forgetGuards();
    $test->withToken($tokenAsesi)->postJson("{$url}/setujui", ['tanda_tangan' => pngDataUrl()])
        ->assertOk()
        ->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_SELESAI);
}

describe('FR.IA.01', function () {
    it('struktur unit/elemen/KUK dari skema & isian awal TUK dari AK', function () {
        [$p, $token, $tokenAsesi] = iaSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-01";
        $elemen = ElemenKompetensi::whereHas('unitKompetensi', fn ($q) => $q->where('skema_sertifikasi_id', $p->pendaftaran->skema_sertifikasi_id))->orderBy('id')->first();

        $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.isian.tuk', 'tempat_kerja')
            ->assertJsonPath('data.isian.tanggal', '2026-11-01')
            ->assertJsonCount(2, 'data.struktur')
            ->assertJsonCount(2, 'data.struktur.0.elemen')
            ->assertJsonPath('data.struktur.0.elemen.0.kunci', 'e'.$elemen->id)
            ->assertJsonPath('data.struktur.0.elemen.0.kuk.0.deskripsi', 'KUK 1.1.1')
            ->assertJsonPath("data.isian.elemen.e{$elemen->id}.pencapaian", null);
        app('auth')->forgetGuards();

        $this->withToken($tokenAsesi)->getJson($url)->assertForbidden()->assertJsonPath('message', 'Dokumen sedang disiapkan asesor.');
    });

    it('validasi kirim: pencapaian, rekomendasi konsisten, umpan balik; lalu alur persetujuan', function () {
        [$p, $token, $tokenAsesi] = iaSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-01";
        $kunci = collect($this->withToken($token)->getJson($url)->json('data.struktur'))->flatMap(fn ($u) => array_column($u['elemen'], 'kunci'))->all();

        // Kunci tak dikenal dibuang; PATCH parsial per elemen tidak menghapus elemen lain.
        $this->withToken($token)->patchJson($url, ['isian' => ['elemen' => [$kunci[0] => ['pencapaian' => 'tidak'], 'e999999' => ['pencapaian' => 'ya']]]])
            ->assertOk()
            ->assertJsonMissingPath('data.isian.elemen.e999999');
        $this->withToken($token)->patchJson($url, ['isian' => ['elemen' => [$kunci[1] => ['standar' => 'SOP registrasi']], 'rekomendasi' => 'kompeten']])
            ->assertOk()
            ->assertJsonPath("data.isian.elemen.{$kunci[0]}.pencapaian", 'tidak');
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")
            ->assertJsonValidationErrors(["isian.elemen.{$kunci[1]}", 'isian.rekomendasi', 'isian.umpan_balik']);

        $semua = collect($kunci)->mapWithKeys(fn ($k) => [$k => ['pencapaian' => 'ya']])->all();
        $this->withToken($token)->patchJson($url, ['isian' => ['elemen' => $semua, 'umpan_balik' => 'Bagus.', 'lanjutan' => ['unit' => 'diabaikan']]])
            ->assertOk()
            ->assertJsonPath('data.isian.lanjutan.unit', null);
        kirimDanSetujui($this, $url, $token, $tokenAsesi);

        expect(MukFixture::dokumen($p, JenisDokumenMuk::IA_01)->snapshot['struktur'])->toHaveCount(2);
    });
});

describe('FR.IA.02', function () {
    it('skenario, waktu & penyusun dari template TPD; dibekukan saat dikirim', function () {
        [$p, $token, $tokenAsesi, $template] = iaSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-02";

        $res = $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.instrumen.waktu', '20 Menit')
            ->assertJsonPath('data.penyusun.0.nama', 'Penyusun IA')
            ->assertJsonPath('data.penyusun.0.ada_ttd', true)
            ->assertJsonPath('data.penyusun.0.tanggal', null)
            ->assertJsonCount(2, 'data.unit_kompetensi');
        expect(json_encode($res->json()))->not->toContain('_path');

        $this->withToken($token)->patchJson($url, ['isian' => ['skenario' => 'ubah']])->assertJsonValidationErrors('isian');
        $this->withToken($token)->patchJson($url, ['isian' => ['tuk' => 'sewaktu']])->assertOk();
        kirimDanSetujui($this, $url, $token, $tokenAsesi);

        $template->update(['instrumen' => [MapaTemplate::IA_02 => ['skenario' => 'Skenario baru', 'waktu' => '1 Jam']]]);
        $pid = $template->penandatangan()->where('bagian', 'penyusun_ia02')->value('id');
        Storage::disk('local')->delete('mapa-template/ttd-ia02.png');

        $this->withToken($tokenAsesi)->getJson($url)
            ->assertJsonPath('data.instrumen.skenario', "Skenario uji\n1. Unit 1")
            // Tanggal ttd penyusun = tanggal dokumen dikirim asesor.
            ->assertJsonPath('data.penyusun.0.tanggal', now('Asia/Jakarta')->toDateString());
        $this->withToken($tokenAsesi)->get("{$url}/penandatangan/{$pid}")->assertOk()->assertHeader('Content-Type', 'image/png');
    });

    it('tidak bisa dikirim bila skenario belum diisi di CMS', function () {
        [$p, $token, , $template] = iaSiap();
        $template->update(['instrumen' => null]);
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-02";

        $this->withToken($token)->patchJson($url, ['isian' => ['tuk' => 'sewaktu']])->assertOk();
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors('instrumen');
    });
});

describe('FR.IA.03', function () {
    it('asesor mencatat tanggapan, Ya/Tidak & umpan balik, ttd -> asesi menyetujui', function () {
        [$p, $token, $tokenAsesi, $template] = iaSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-03";

        // Asesi belum bisa melihat sebelum asesor mengirim.
        $this->withToken($tokenAsesi)->getJson($url)->assertForbidden();
        app('auth')->forgetGuards();

        $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.isian.tuk', 'tempat_kerja')
            ->assertJsonPath('data.isian.pertanyaan', ['Pertanyaan satu?', 'Pertanyaan dua?'])
            ->assertJsonPath('data.dokumen.penyetuju', 'asesi');
        // Pertanyaan template tidak bisa dikirim klien.
        $this->withToken($token)->patchJson($url, ['isian' => ['pertanyaan' => ['palsu']]])->assertJsonValidationErrors('isian');
        $this->withToken($token)->patchJson($url, ['isian' => [
            'jawaban' => [['tanggapan' => 'Pakai APD lengkap', 'pencapaian' => 'ya'], ['pencapaian' => 'tidak']],
            'tambahan' => [['teks' => 'Pertanyaan asesor?', 'tanggapan' => 'Sesuai SOP', 'pencapaian' => 'ya'], ['teks' => '']],
        ]])->assertOk()->assertJsonCount(1, 'data.isian.tambahan');

        // Template berubah setelah disimpan -> pertanyaan dokumen tetap.
        $template->update(['instrumen' => [MapaTemplate::IA_03 => ['pertanyaan' => ['Lain?']]]]);
        $this->withToken($token)->getJson($url)->assertJsonPath('data.isian.pertanyaan.0', 'Pertanyaan satu?');

        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors(['isian.jawaban.1', 'isian.umpan_balik']);
        $this->withToken($token)->patchJson($url, ['isian' => [
            'jawaban' => [['tanggapan' => 'Pakai APD lengkap', 'pencapaian' => 'ya'], ['tanggapan' => 'Belum tahu', 'pencapaian' => 'tidak']],
            'umpan_balik' => 'Perdalam K3.',
        ]])->assertOk();
        // Endpoint pola lama tidak berlaku lagi.
        $this->withToken($token)->postJson("{$url}/buka")->assertNotFound();
        kirimDanSetujui($this, $url, $token, $tokenAsesi);

        $this->withToken($tokenAsesi)->getJson($url)->assertOk()
            ->assertJsonPath('data.isian.jawaban.1.tanggapan', 'Belum tahu')
            ->assertJsonPath('data.isian.umpan_balik', 'Perdalam K3.');
        app('auth')->forgetGuards();

        // AK.02: pencapaian "tidak" IA.03 tampil sebagai indikasi.
        $p->dokumenPenilaian()->with('jenisDokumen')->get()
            ->filter(fn ($d) => $d->jenisDokumen->kategori === 'IA')
            ->each(fn ($d) => $d->update(['status' => DokumenPenilaian::STATUS_SELESAI]));
        expect(collect($this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/ak-02")->json('data.indikasi'))->pluck('keterangan')->all())
            ->toContain('1 pertanyaan belum tercapai');
    });

    it('tidak bisa dikirim tanpa pertanyaan', function () {
        [$p, $token, , $template] = iaSiap();
        $template->update(['instrumen' => null]);
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ia-03";

        $this->withToken($token)->patchJson($url, ['isian' => ['umpan_balik' => 'x']])->assertOk();
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors('isian.jawaban');
    });
});

it('migrasi IA.03 ke alur diisi asesor: selesai dikonversi, berjalan jadi draft asesor, down() memulihkan', function () {
    [$p, $token] = iaSiap();
    $dok = MukFixture::dokumen($p, JenisDokumenMuk::IA_03);
    $reviewLama = [
        'persiapan' => ['tuk' => 'sewaktu', 'tanggal' => '2026-11-01', 'tambahan' => ['Q3?'], 'pertanyaan' => ['Q1?', 'Q2?'], 'dibuka_at' => '2026-10-20T10:00:00+00:00'],
        'penilaian' => ['pencapaian' => ['ya', 'tidak', 'ya'], 'umpan_balik' => 'UB'],
    ];
    $dok->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'data' => ['jawaban' => ['T1', 'T2', 'T3']], 'data_review' => $reviewLama, 'submitted_at' => now()]);

    $migrasi = require database_path('migrations/2026_10_27_100000_fr_ia_03_tanggapan_diisi_asesor.php');
    $migrasi->up();
    $migrasi->up(); // idempoten

    expect($dok->fresh()->status)->toBe(DokumenPenilaian::STATUS_SELESAI);
    $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/ia-03")->assertOk()
        ->assertJsonPath('data.isian.pertanyaan', ['Q1?', 'Q2?'])
        ->assertJsonPath('data.isian.jawaban', [['tanggapan' => 'T1', 'pencapaian' => 'ya'], ['tanggapan' => 'T2', 'pencapaian' => 'tidak']])
        ->assertJsonPath('data.isian.tambahan', [['teks' => 'Q3?', 'tanggapan' => 'T3', 'pencapaian' => 'ya']])
        ->assertJsonPath('data.isian.umpan_balik', 'UB');

    $migrasi->down();
    expect($dok->fresh()->data)->toBe(['jawaban' => ['T1', 'T2', 'T3']])
        ->and($dok->fresh()->data_review)->toEqual($reviewLama);

    // Masih berjalan (menunggu penilaian asesor) -> draft asesor, ttd asesi dikosongkan, tanggapan dipertahankan.
    $dok->update(['status' => DokumenPenilaian::STATUS_MENUNGGU_REVIEW, 'path_tanda_tangan_asesi' => 'signatures/x.png', 'tanggal_tanda_tangan_asesi' => now()]);
    $migrasi->up();
    $baru = $dok->fresh();
    expect($baru->status)->toBe(DokumenPenilaian::STATUS_DRAFT)
        ->and($baru->path_tanda_tangan_asesi)->toBeNull()
        ->and($baru->data['jawaban'][0]['tanggapan'])->toBe('T1');
    $migrasi->down();
    expect($dok->fresh()->status)->toBe(DokumenPenilaian::STATUS_MENUNGGU_REVIEW)
        ->and($dok->fresh()->path_tanda_tangan_asesi)->toBe('signatures/x.png');
});
