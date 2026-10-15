<?php

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\ElemenKompetensi;
use App\Models\JenisDokumenMuk;
use App\Models\PenilaianMuk;
use App\Models\RiwayatDokumenPenilaian;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MukFixture;

beforeEach(function () {
    Storage::fake('local');
});

function gantiUser(): void
{
    app('auth')->forgetGuards();
}

/**
 * Penilaian dengan APL.02 sudah dikirim asesi (lewat API). APL.01 diterima admin bila $apl01Diterima.
 *
 * @return array{0: PenilaianMuk, 1: Asesor, 2: string, 3: string} [penilaian, asesor, token asesor, token asesi]
 */
function apl02Terkirim($test, bool $apl01Diterima = true): array
{
    $asesor = Asesor::factory()->create();
    $asesi = Asesi::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);
    MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->update($apl01Diterima
        ? ['status' => DokumenPenilaian::STATUS_SELESAI, 'hasil_rekomendasi_admin' => 'diterima']
        : ['status' => DokumenPenilaian::STATUS_MENUNGGU_REVIEW]);

    $tokenAsesi = MukFixture::token($asesi);
    $url = "/api/muk/penilaian/{$p->id}/dokumen/apl-02";
    $jawaban = ElemenKompetensi::whereHas('unitKompetensi', fn ($q) => $q->where('skema_sertifikasi_id', $p->pendaftaran->skema_sertifikasi_id))
        ->pluck('id')
        ->map(fn ($id) => ['elemen_id' => $id, 'kompeten' => 'K', 'bukti' => [['id' => null, 'link' => "https://bukti.test/{$id}"]]])
        ->all();

    $test->withToken($tokenAsesi)->patchJson($url, ['jawaban' => $jawaban])->assertOk();
    $test->withToken($tokenAsesi)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
    $test->withToken($tokenAsesi)->postJson("{$url}/submit")->assertOk();
    gantiUser();

    return [$p, $asesor, MukFixture::token($asesor), $tokenAsesi];
}

function reviewUrl(PenilaianMuk $p): string
{
    return "/api/muk/penilaian/{$p->id}/dokumen/apl-02/review";
}

describe('review FR.APL.02 oleh asesor', function () {
    it('GET memberi info review untuk asesor', function () {
        [$p, , $token] = apl02Terkirim($this);

        $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/apl-02")
            ->assertJsonPath('data.dokumen.peninjau', 'asesor')
            ->assertJsonPath('data.dokumen.review', ['bisa' => true, 'alasan' => null]);
    });

    it('ditolak sebelum APL.01 diterima admin', function () {
        [$p, , $token] = apl02Terkirim($this, apl01Diterima: false);

        $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/apl-02")
            ->assertJsonPath('data.dokumen.review.bisa', false)
            ->assertJsonPath('data.dokumen.review.alasan', 'Menunggu FR.APL.01 diterima admin LSP.');
        $this->withToken($token)->postJson(reviewUrl($p), ['hasil' => 'dapat_dilanjutkan', 'tanda_tangan' => pngDataUrl()])
            ->assertForbidden();
    });

    it('dapat dilanjutkan: ttd asesor wajib, status selesai, asesi melihat hasil', function () {
        [$p, $asesor, $token, $tokenAsesi] = apl02Terkirim($this);

        $this->withToken($token)->postJson(reviewUrl($p), ['hasil' => 'dapat_dilanjutkan'])
            ->assertJsonValidationErrors('tanda_tangan');

        $this->withToken($token)->postJson(reviewUrl($p), ['hasil' => 'dapat_dilanjutkan', 'tanda_tangan' => pngDataUrl()])
            ->assertOk()
            ->assertJsonPath('data.dokumen.status', 'selesai')
            ->assertJsonPath('data.dokumen.tanda_tangan.asesor.ada', true)
            ->assertJsonPath('data.peninjau.rekomendasi', 'dapat_dilanjutkan')
            ->assertJsonPath('data.peninjau.nomor_registrasi', $asesor->nomor_registrasi);

        expect(MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->ditinjau_oleh_asesor_id)->toBe($asesor->id)
            ->and(RiwayatDokumenPenilaian::where('aksi', 'review_asesor_diterima')->exists())->toBeTrue();

        gantiUser();
        $this->withToken($tokenAsesi)->getJson("/api/muk/penilaian/{$p->id}/dokumen/apl-02")
            ->assertJsonPath('data.dokumen.hasil_rekomendasi_asesor', 'dapat_dilanjutkan')
            ->assertJsonMissingPath('data.dokumen.review');

        // Tidak bisa ditinjau dua kali.
        gantiUser();
        $this->withToken($token)->postJson(reviewUrl($p), ['hasil' => 'dapat_dilanjutkan', 'tanda_tangan' => pngDataUrl()])
            ->assertForbidden();
    });

    it('tidak dapat dilanjutkan: catatan wajib, dikembalikan ke asesi untuk revisi', function () {
        [$p, , $token, $tokenAsesi] = apl02Terkirim($this);

        $this->withToken($token)->postJson(reviewUrl($p), ['hasil' => 'tidak_dapat_dilanjutkan'])
            ->assertJsonValidationErrors('catatan');

        $this->withToken($token)->postJson(reviewUrl($p), ['hasil' => 'tidak_dapat_dilanjutkan', 'catatan' => 'Bukti elemen 1 kurang relevan.'])
            ->assertOk();

        $dok = MukFixture::dokumen($p, JenisDokumenMuk::APL_02);
        expect($dok->status)->toBe('draft')
            ->and($dok->path_tanda_tangan_asesi)->toBeNull()
            ->and($p->fresh()->status)->toBe('menunggu_pengisian_apl');

        gantiUser();
        $this->withToken($tokenAsesi)->getJson("/api/muk/penilaian/{$p->id}/dokumen/apl-02")
            ->assertJsonPath('data.dokumen.dapat_diubah', true)
            ->assertJsonPath('data.peninjau.catatan', 'Bukti elemen 1 kurang relevan.');
    });

    it('asesi, asesor lain, dan nilai hasil asing ditolak', function () {
        [$p, , $token, $tokenAsesi] = apl02Terkirim($this);

        $this->withToken($tokenAsesi)->postJson(reviewUrl($p), ['hasil' => 'dapat_dilanjutkan', 'tanda_tangan' => pngDataUrl()])
            ->assertForbidden();
        gantiUser();
        $this->withToken(MukFixture::token(Asesor::factory()->create()))->postJson(reviewUrl($p), ['hasil' => 'dapat_dilanjutkan'])
            ->assertNotFound();
        gantiUser();
        $this->withToken($token)->postJson(reviewUrl($p), ['hasil' => 'diterima', 'tanda_tangan' => pngDataUrl()])
            ->assertJsonValidationErrors('hasil');
        // Asesor tidak bisa "meninjau" APL.01 (peninjaunya admin).
        $this->withToken($token)->postJson("/api/muk/penilaian/{$p->id}/dokumen/apl-01/review", ['hasil' => 'diterima'])
            ->assertForbidden();
    });
});

describe('tentukan metode', function () {
    it('belum bisa sebelum APL.02 dapat dilanjutkan', function () {
        [$p, , $token] = apl02Terkirim($this);

        $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}")
            ->assertJsonPath('data.aksi_metode.bisa', false);
        $this->withToken($token)->postJson("/api/muk/penilaian/{$p->id}/metode", ['metode' => 'kt'])
            ->assertStatus(409);
    });

    it('setelah review positif: metode tersimpan, penilaian berlangsung, masih bisa diubah', function () {
        [$p, $asesor, $token, $tokenAsesi] = apl02Terkirim($this);
        $this->withToken($token)->postJson(reviewUrl($p), ['hasil' => 'dapat_dilanjutkan', 'tanda_tangan' => pngDataUrl()])->assertOk();

        $this->withToken($token)->getJson('/api/muk/penilaian?kelompok=perlu_tindakan')->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}")->assertJsonPath('data.aksi_metode.bisa', true);

        $this->withToken($token)->postJson("/api/muk/penilaian/{$p->id}/metode", ['metode' => 'tpd'])
            ->assertOk()
            ->assertJsonPath('data.metode_asesmen', 'tpd')
            ->assertJsonPath('data.status', 'berlangsung');

        expect($p->fresh()->ditentukan_oleh_asesor_id)->toBe($asesor->id)
            ->and(RiwayatDokumenPenilaian::where('aksi', 'metode_ditentukan')->value('meta'))->toBe(['metode' => 'tpd', 'sebelumnya' => null]);

        // Ubah selama belum ada dokumen turunan.
        $this->withToken($token)->postJson("/api/muk/penilaian/{$p->id}/metode", ['metode' => 'vp'])
            ->assertJsonPath('data.metode_asesmen', 'vp');
        $this->withToken($token)->getJson('/api/muk/penilaian?kelompok=perlu_tindakan')->assertJsonCount(0, 'data');

        // Metode tidak valid & asesi ditolak.
        $this->withToken($token)->postJson("/api/muk/penilaian/{$p->id}/metode", ['metode' => 'xx'])->assertJsonValidationErrors('metode');
        gantiUser();
        $this->withToken($tokenAsesi)->postJson("/api/muk/penilaian/{$p->id}/metode", ['metode' => 'kt'])->assertForbidden();
    });

    it('menetapkan metode membuat dokumen sesuai metode; ganti metode membuat ulang selama belum diisi', function () {
        [$p, , $token] = apl02Terkirim($this);
        $this->withToken($token)->postJson(reviewUrl($p), ['hasil' => 'dapat_dilanjutkan', 'tanda_tangan' => pngDataUrl()])->assertOk();
        $kode = fn () => $p->fresh()->dokumenPenilaian()->with('jenisDokumen')->get()->pluck('jenisDokumen.kode')->sort()->values()->all();

        $this->withToken($token)->postJson("/api/muk/penilaian/{$p->id}/metode", ['metode' => 'kt'])->assertOk();
        expect($kode())->toHaveCount(2 + 14)
            ->toContain('FR.MAPA.01', 'FR.MAPA.02', 'FR.IA.04A', 'FR.IA.04B')
            ->not->toContain('FR.IA.08');

        // Sidebar: MAPA terbuka untuk asesor, AK/IA terkunci sampai MAPA selesai.
        $dok = collect($this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}")->json('data.dokumen'))->keyBy('kode');
        expect($dok['FR.MAPA.01']['terkunci'])->toBeFalse()
            ->and($dok['FR.IA.04A']['terkunci'])->toBeTrue()
            ->and($dok->keys()->slice(0, 4)->values()->all())->toBe(['FR.APL.01', 'FR.APL.02', 'FR.MAPA.01', 'FR.MAPA.02']);

        // Ganti ke VP: dokumen lama dihapus, dibuat ulang sesuai VP (17 total).
        $this->withToken($token)->postJson("/api/muk/penilaian/{$p->id}/metode", ['metode' => 'vp'])->assertOk();
        expect($kode())->toHaveCount(2 + 15)->toContain('FR.IA.08', 'FR.IA.09', 'FR.IA.10')->not->toContain('FR.IA.04A');

        // Setelah salah satu dokumen turunan mulai diisi, metode terkunci.
        MukFixture::dokumen($p, 'FR.MAPA.01')->update(['status' => 'draft']);
        $this->withToken($token)->postJson("/api/muk/penilaian/{$p->id}/metode", ['metode' => 'tpd'])
            ->assertStatus(409);
        expect($p->fresh()->metode_asesmen)->toBe('vp');
    });

    it('asesi tidak bisa membuka MAPA (diisi asesor)', function () {
        [$p, , $token, $tokenAsesi] = apl02Terkirim($this);
        $this->withToken($token)->postJson(reviewUrl($p), ['hasil' => 'dapat_dilanjutkan', 'tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson("/api/muk/penilaian/{$p->id}/metode", ['metode' => 'tpd'])->assertOk();
        gantiUser();

        $dok = collect($this->withToken($tokenAsesi)->getJson("/api/muk/penilaian/{$p->id}")->json('data.dokumen'))->keyBy('kode');
        expect($dok['FR.MAPA.01']['alasan_terkunci'])->toBe('Dokumen ini diisi oleh asesor.')
            ->and($dok['FR.IA.02']['terkunci'])->toBeTrue()
            ->and($dok)->toHaveCount(2 + 15);
    });

    it('asesor lain tidak bisa menentukan metode (404)', function () {
        [$p] = apl02Terkirim($this);

        $this->withToken(MukFixture::token(Asesor::factory()->create()))->postJson("/api/muk/penilaian/{$p->id}/metode", ['metode' => 'kt'])
            ->assertNotFound();
    });
});
