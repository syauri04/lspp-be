<?php

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\PenilaianMuk;
use App\Models\RiwayatDokumenPenilaian;
use App\Notifications\Muk\DokumenPersetujuanNotifikasi;
use App\Services\Muk\MetodeAsesmenService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MukFixture;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

/**
 * Penilaian VP dengan APL & MAPA selesai (dokumen AK terbuka).
 *
 * @return array{0: PenilaianMuk, 1: string, 2: string, 3: Asesi, 4: Asesor} [penilaian, token asesor, token asesi, asesi, asesor]
 */
function akSiap(): array
{
    $asesor = Asesor::factory()->create();
    $asesi = Asesi::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);
    $p->jadwalAsesmen->update(['waktu_mulai' => '08:00:00', 'waktu_selesai' => '12:00:00']);

    MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_admin' => 'diterima']);
    MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->update(['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_asesor' => 'dapat_dilanjutkan']);
    app(MetodeAsesmenService::class)->tetapkan($p, 'vp', $asesor);

    MukFixture::dokumen($p, JenisDokumenMuk::MAPA_01)->update([
        'status' => DokumenPenilaian::STATUS_SELESAI,
        'data' => ['asesi' => ['3']],
        'snapshot' => [
            'standar_kompetensi' => ['SKKNI Nomor 123 Tahun 2024'],
            'rencana' => [['unit_id' => 1, 'tanya_jawab' => 'PW, DPT', 'verifikasi_portofolio' => 'CVP', 'verifikasi_pihak_ketiga' => null]],
        ],
    ]);
    MukFixture::dokumen($p, JenisDokumenMuk::MAPA_02)->update(['status' => DokumenPenilaian::STATUS_SELESAI]);

    return [$p->fresh(), MukFixture::token($asesor), MukFixture::token($asesi), $asesi, $asesor];
}

function isianAk07Lengkap(): array
{
    $karakter = collect(range(1, 8))->mapWithKeys(fn ($i) => ['k'.$i => ['penyesuaian' => 'tidak']])->all();
    $karakter['k4'] = ['penyesuaian' => 'ya', 'pilihan' => [2], 'lainnya' => [['teks' => 'Istirahat 15 menit', 'checked' => true]]];

    return ['tuk' => 'sewaktu', 'karakter' => $karakter];
}

function gantiPengguna(): void
{
    app('auth')->forgetGuards();
}

describe('FR.AK.07', function () {
    it('isian awal otomatis dari jadwal, MAPA.01, dan metode', function () {
        [$p, $token] = akSiap();

        $res = $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/ak-07")
            ->assertOk()
            ->assertJsonPath('data.dokumen.pengisi', 'asesor')
            ->assertJsonPath('data.dokumen.penyetuju', 'asesi')
            ->assertJsonPath('data.prefill', true)
            ->assertJsonPath('data.isian.tanggal', '2026-11-01')
            ->assertJsonPath('data.isian.potensi', ['3'])
            ->assertJsonPath('data.isian.hasil.acuan', 'SKKNI Nomor 123 Tahun 2024')
            ->assertJsonPath('data.isian.hasil.metode', 'Verifikasi Portofolio')
            ->assertJsonPath('data.info.asesor.nama', $p->jadwalAsesmen->asesor->nama)
            ->assertJsonCount(8, 'data.karakter');

        expect($res->json('data.isian.hasil.instrumen'))->toContain('FR.IA.08')->not->toContain('FR.IA.06B');
    });

    it('asesi belum bisa melihat sebelum dikirim & tidak bisa mengisi', function () {
        [$p, , $tokenAsesi] = akSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ak-07";

        $this->withToken($tokenAsesi)->getJson($url)->assertForbidden()->assertJsonPath('message', 'Dokumen sedang disiapkan asesor.');
        $this->withToken($tokenAsesi)->patchJson($url, ['isian' => isianAk07Lengkap()])->assertForbidden();
    });

    it('validasi & penggabungan isian per baris', function () {
        [$p, $token] = akSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ak-07";

        $this->withToken($token)->patchJson($url, ['isian' => ['tuk' => 'kantor']])->assertJsonValidationErrors('isian.tuk');
        $this->withToken($token)->patchJson($url, ['isian' => ['karakter' => ['k9' => ['penyesuaian' => 'ya']]]])->assertJsonValidationErrors('isian.karakter');
        $this->withToken($token)->patchJson($url, ['isian' => ['asing' => 1]])->assertJsonValidationErrors('isian');

        $this->withToken($token)->patchJson($url, ['isian' => ['karakter' => ['k1' => ['penyesuaian' => 'ya', 'pilihan' => [4, 0, 0]]]]])
            ->assertOk()
            ->assertJsonPath('data.isian.karakter.k1.pilihan', [0, 4])
            ->assertJsonPath('data.isian.potensi', ['3']);
        // Baris lain tidak terhapus; "tidak" mengosongkan pilihan.
        $this->withToken($token)->patchJson($url, ['isian' => ['karakter' => ['k2' => ['penyesuaian' => 'tidak', 'pilihan' => [1]]]]])
            ->assertOk()
            ->assertJsonPath('data.isian.karakter.k1.pilihan', [0, 4])
            ->assertJsonPath('data.isian.karakter.k2.pilihan', []);
    });

    it('alur lengkap: asesor isi + ttd + kirim, asesi menyetujui dengan ttd -> selesai', function () {
        [$p, $token, $tokenAsesi, $asesi, $asesor] = akSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ak-07";

        $this->withToken($token)->patchJson($url, ['isian' => ['karakter' => ['k1' => ['penyesuaian' => 'ya']]]])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")
            ->assertJsonValidationErrors(['isian.tuk', 'isian.karakter.k1', 'isian.karakter.k2', 'tanda_tangan']);

        $this->withToken($token)->patchJson($url, ['isian' => isianAk07Lengkap()])->assertOk();
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")
            ->assertOk()
            ->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_MENUNGGU_PERSETUJUAN)
            ->assertJsonPath('data.dokumen.tanda_tangan.asesor.ada', true)
            ->assertJsonPath('data.dokumen.dapat_ditarik', true);
        Notification::assertSentTo($asesi, DokumenPersetujuanNotifikasi::class);
        expect(PenilaianMuk::perluTindakan('asesi')->whereKey($p->id)->exists())->toBeTrue();

        // Asesor tidak bisa menyetujui dokumennya sendiri.
        $this->withToken($token)->postJson("{$url}/setujui", ['tanda_tangan' => pngDataUrl()])->assertForbidden();
        gantiPengguna();

        $this->withToken($tokenAsesi)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.dokumen.persetujuan.bisa', true)
            ->assertJsonPath('data.dokumen.dapat_diubah', false);
        $this->withToken($tokenAsesi)->postJson("{$url}/setujui", ['tanda_tangan' => 'bukan-png'])->assertJsonValidationErrors('tanda_tangan');
        $this->withToken($tokenAsesi)->postJson("{$url}/setujui", ['tanda_tangan' => pngDataUrl()])
            ->assertOk()
            ->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_SELESAI)
            ->assertJsonPath('data.dokumen.tanda_tangan.asesi.ada', true)
            ->assertJsonPath('data.dokumen.tanda_tangan.asesor.ada', true)
            ->assertJsonPath('data.dokumen.persetujuan.bisa', false);
        // Asesmen tatap muka: email "sudah disetujui" ke asesor nonaktif secara default.
        Notification::assertNotSentTo($asesor, DokumenPersetujuanNotifikasi::class);

        $this->withToken($tokenAsesi)->postJson("{$url}/setujui", ['tanda_tangan' => pngDataUrl()])->assertForbidden();
        expect(RiwayatDokumenPenilaian::where('aksi', 'disetujui_asesi')->exists())->toBeTrue()
            ->and(PenilaianMuk::perluTindakan('asesi')->whereKey($p->id)->exists())->toBeFalse();
    });

    it('asesor dapat menarik kembali sebelum disetujui', function () {
        [$p, $token, $tokenAsesi] = akSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ak-07";
        $this->withToken($token)->patchJson($url, ['isian' => isianAk07Lengkap()])->assertOk();
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertOk();

        $this->withToken($token)->postJson("{$url}/tarik")
            ->assertOk()
            ->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_DRAFT)
            ->assertJsonPath('data.dokumen.dapat_diubah', true);
        $this->withToken($token)->postJson("{$url}/tarik")->assertForbidden();
        gantiPengguna();

        $this->withToken($tokenAsesi)->postJson("{$url}/setujui", ['tanda_tangan' => pngDataUrl()])->assertForbidden();
        $this->withToken($tokenAsesi)->postJson("{$url}/tarik")->assertForbidden();
    });

    it('asesi lain mendapat 404', function () {
        [$p] = akSiap();
        $lain = MukFixture::token(Asesi::factory()->create());

        $this->withToken($lain)->postJson("/api/muk/penilaian/{$p->id}/dokumen/ak-07/setujui", ['tanda_tangan' => pngDataUrl()])->assertNotFound();
    });
});

describe('FR.AK.01', function () {
    it('isian awal: TUK dari AK.07, bukti dari rencana MAPA.01, pelaksanaan dari jadwal', function () {
        [$p, $token] = akSiap();
        MukFixture::dokumen($p, JenisDokumenMuk::AK_07)->update(['data' => ['tuk' => 'tempat_kerja']]);

        $res = $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/ak-01")
            ->assertOk()
            ->assertJsonPath('data.isian.tuk', 'tempat_kerja')
            ->assertJsonPath('data.isian.pelaksanaan.tanggal', '2026-11-01')
            ->assertJsonPath('data.isian.pelaksanaan.waktu', '08:00 - 12:00 WIB')
            ->assertJsonPath('data.pernyataan.asesor', fn ($v) => str_contains($v, 'tidak akan membuka hasil pekerjaan'));

        expect($res->json('data.isian.bukti'))->toEqualCanonicalizing(['portofolio', 'tertulis', 'wawancara'])
            ->and($res->json('data.isian.pelaksanaan.tuk'))->toContain('TUK Uji');
    });

    it('validasi kirim: bukti lainnya wajib ditulis; lalu alur persetujuan sampai selesai', function () {
        [$p, $token, $tokenAsesi] = akSiap();
        $url = "/api/muk/penilaian/{$p->id}/dokumen/ak-01";

        $this->withToken($token)->patchJson($url, ['isian' => ['tuk' => 'sewaktu', 'bukti' => ['lainnya'], 'bukti_lainnya' => '']])->assertOk();
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertJsonValidationErrors('isian.bukti');

        $this->withToken($token)->patchJson($url, ['isian' => ['bukti_lainnya' => 'Hasil Verifikasi Pihak Ketiga']])
            ->assertOk()
            // Isi berubah setelah ditandatangani -> tanda tangan asesor dihapus.
            ->assertJsonPath('data.dokumen.tanda_tangan.asesor.ada', false);
        $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("{$url}/submit")->assertOk()->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_MENUNGGU_PERSETUJUAN);
        gantiPengguna();

        $this->withToken($tokenAsesi)->postJson("{$url}/setujui", ['tanda_tangan' => pngDataUrl()])
            ->assertOk()
            ->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_SELESAI)
            ->assertJsonPath('data.isian.bukti_lainnya', 'Hasil Verifikasi Pihak Ketiga');
    });
});

describe('akses dokumen IA', function () {
    it('IA terkunci sampai AK.07 & AK.01 selesai; AK.07/AK.01 bebas urutan', function () {
        [$p, $token, $tokenAsesi] = akSiap();
        $ia = fn (string $tkn) => collect($this->withToken($tkn)->getJson("/api/muk/penilaian/{$p->id}")->assertOk()->json('data.dokumen'))
            ->keyBy('kode');

        $dok = $ia($token);
        expect($dok['FR.IA.08']['terkunci'])->toBeTrue()
            ->and($dok['FR.IA.08']['alasan_terkunci'])->toBe('Menunggu FR.AK.07 dan FR.AK.01 diisi asesor dan disetujui asesi.')
            ->and($dok['FR.AK.07']['terkunci'])->toBeFalse()
            ->and($dok['FR.AK.01']['terkunci'])->toBeFalse();

        // Baru salah satu selesai -> masih terkunci.
        MukFixture::dokumen($p, JenisDokumenMuk::AK_01)->update(['status' => DokumenPenilaian::STATUS_SELESAI]);
        expect($ia($token)['FR.IA.08']['terkunci'])->toBeTrue();

        // AK.07 menunggu persetujuan belum cukup.
        MukFixture::dokumen($p, JenisDokumenMuk::AK_07)->update(['status' => DokumenPenilaian::STATUS_MENUNGGU_PERSETUJUAN]);
        expect($ia($token)['FR.IA.08']['terkunci'])->toBeTrue();

        MukFixture::dokumen($p, JenisDokumenMuk::AK_07)->update(['status' => DokumenPenilaian::STATUS_SELESAI]);
        expect($ia($token)['FR.IA.08']['terkunci'])->toBeFalse();
        gantiPengguna();
        // Bagi asesi, IA.08 (diisi asesor) baru terbuka setelah dikirim asesor.
        expect($ia($tokenAsesi)['FR.IA.08']['alasan_terkunci'])->toBe('Dokumen sedang disiapkan asesor.');
    });
});
