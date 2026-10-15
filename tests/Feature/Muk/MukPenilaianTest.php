<?php

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\PenilaianMuk;
use Illuminate\Support\Facades\DB;
use Tests\Support\MukFixture;

function dokumenDari(array $item, string $kode): array
{
    return collect($item['dokumen'])->firstWhere('kode', $kode);
}

describe('kepemilikan asesi', function () {
    it('asesi hanya melihat penilaian miliknya', function () {
        $asesiA = Asesi::factory()->create();
        $milikA = MukFixture::penilaian(asesi: $asesiA);
        MukFixture::penilaian(); // milik asesi lain

        $this->withToken(MukFixture::token($asesiA))->getJson('/api/muk/penilaian')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $milikA->id)
            ->assertJsonPath('meta.total', 1);
    });

    it('asesi A mendapat 404 untuk penilaian asesi B, tanpa membocorkan nama model', function () {
        $milikB = MukFixture::penilaian();

        $this->withToken(MukFixture::token(Asesi::factory()->create()))
            ->getJson("/api/muk/penilaian/{$milikB->id}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Data tidak ditemukan.']);
    });

    it('penilaian dengan jadwal dibatalkan disembunyikan', function () {
        $asesi = Asesi::factory()->create();
        $p = MukFixture::penilaian(asesi: $asesi);
        $p->jadwalAsesmen->update(['status' => 'dibatalkan']);

        $this->withToken(MukFixture::token($asesi))->getJson('/api/muk/penilaian')->assertJsonCount(0, 'data');
        $this->withToken(MukFixture::token($asesi))->getJson("/api/muk/penilaian/{$p->id}")->assertNotFound();
    });
});

describe('kepemilikan asesor', function () {
    it('asesor dengan 2 jadwal melihat keduanya, bukan jadwal asesor lain', function () {
        $asesor = Asesor::factory()->create();
        MukFixture::penilaian(asesor: $asesor, tanggal: '2026-11-01');
        MukFixture::penilaian(asesor: $asesor, tanggal: '2026-12-01');
        $lain = MukFixture::penilaian();

        $res = $this->withToken(MukFixture::token($asesor))->getJson('/api/muk/penilaian')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        expect(collect($res->json('data'))->pluck('id'))->not->toContain($lain->id)
            ->and($res->json('data.0.jadwal.tanggal'))->toBe('2026-12-01'); // terbaru dulu

        $this->withToken(MukFixture::token($asesor))->getJson("/api/muk/penilaian/{$lain->id}")->assertNotFound();
    });

    it('asesor lama kehilangan akses saat admin mengganti asesor di jadwal', function () {
        $lama = Asesor::factory()->create();
        $p = MukFixture::penilaian(asesor: $lama);
        $p->jadwalAsesmen->update(['asesor_id' => Asesor::factory()->create()->id]);

        $this->withToken(MukFixture::token($lama))->getJson("/api/muk/penilaian/{$p->id}")->assertNotFound();
    });

    it('asesi pemilik penilaian tidak dianggap asesor walau ID-nya sama', function () {
        // ID asesi & asesor dari tabel berbeda bisa bentrok; scope harus per model.
        $asesor = Asesor::factory()->create();
        $asesi = Asesi::factory()->create();
        MukFixture::penilaian(asesi: $asesi);

        expect(PenilaianMuk::forAsesor($asesor)->count())->toBe(0);
    });
});

describe('isi respons', function () {
    it('daftar memuat skema, jadwal, TUK, asesor, dan ringkasan dokumen terurut', function () {
        $asesi = Asesi::factory()->create();
        $p = MukFixture::penilaian(asesi: $asesi);

        $this->withToken(MukFixture::token($asesi))->getJson('/api/muk/penilaian')
            ->assertJsonPath('data.0.status', 'menunggu_pengisian_apl')
            ->assertJsonPath('data.0.skema.judul', 'Skema Uji')
            ->assertJsonPath('data.0.jadwal.tuk.nama', 'TUK Uji')
            ->assertJsonPath('data.0.asesor.nama', $p->jadwalAsesmen->asesor->nama)
            ->assertJsonPath('data.0.dokumen.0.kode', 'FR.APL.01')
            ->assertJsonPath('data.0.dokumen.0.slug', 'apl-01')
            ->assertJsonPath('data.0.dokumen.1.kode', 'FR.APL.02')
            ->assertJsonPath('data.0.perlu_tindakan', true)
            ->assertJsonMissingPath('data.0.dokumen.0.terkunci'); // field detail hanya di show
    });

    it('detail asesi: APL.02 terkunci sampai APL.01 dikirim', function () {
        $asesi = Asesi::factory()->create();
        $p = MukFixture::penilaian(asesi: $asesi);
        $token = MukFixture::token($asesi);

        $data = $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}")->assertOk()->json('data');
        expect(dokumenDari($data, 'FR.APL.01')['terkunci'])->toBeFalse()
            ->and(dokumenDari($data, 'FR.APL.02')['terkunci'])->toBeTrue();

        MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->update(['status' => DokumenPenilaian::STATUS_MENUNGGU_REVIEW]);

        $data = $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}")->json('data');
        expect(dokumenDari($data, 'FR.APL.02')['terkunci'])->toBeFalse();
    });

    it('detail asesor: dokumen yang belum dikirim asesi terkunci', function () {
        $asesor = Asesor::factory()->create();
        $p = MukFixture::penilaian(asesor: $asesor);
        MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->update(['status' => DokumenPenilaian::STATUS_MENUNGGU_REVIEW]);

        $data = $this->withToken(MukFixture::token($asesor))->getJson("/api/muk/penilaian/{$p->id}")->json('data');

        expect(dokumenDari($data, 'FR.APL.01')['terkunci'])->toBeTrue()
            ->and(dokumenDari($data, 'FR.APL.02')['terkunci'])->toBeFalse()
            ->and($data['perlu_tindakan'])->toBeFalse(); // APL.01 belum diterima admin
    });
});

describe('filter & paginasi', function () {
    it('kelompok perlu_tindakan untuk asesor: APL.02 menunggu review DAN APL.01 sudah diterima admin', function () {
        $asesor = Asesor::factory()->create();
        $menunggu = MukFixture::penilaian(asesor: $asesor);
        MukFixture::dokumen($menunggu, JenisDokumenMuk::APL_02)->update(['status' => 'menunggu_review']);
        MukFixture::dokumen($menunggu, JenisDokumenMuk::APL_01)->update(['status' => 'selesai', 'hasil_rekomendasi_admin' => 'diterima']);
        $belumDiterima = MukFixture::penilaian(asesor: $asesor); // APL.01 belum diterima admin
        MukFixture::dokumen($belumDiterima, JenisDokumenMuk::APL_02)->update(['status' => 'menunggu_review']);
        MukFixture::penilaian(asesor: $asesor); // asesi belum mengisi

        $this->withToken(MukFixture::token($asesor))->getJson('/api/muk/penilaian?kelompok=perlu_tindakan')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $menunggu->id);
    });

    it('asesor perlu tindakan memilih metode setelah merekomendasikan dapat dilanjutkan', function () {
        $asesor = Asesor::factory()->create();
        $p = MukFixture::penilaian(asesor: $asesor);
        MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->update([
            'status' => 'selesai', 'hasil_rekomendasi_asesor' => 'dapat_dilanjutkan',
        ]);
        $token = MukFixture::token($asesor);

        $this->withToken($token)->getJson('/api/muk/penilaian?kelompok=perlu_tindakan')->assertJsonCount(1, 'data');

        $p->update(['metode_ditentukan_at' => now(), 'status' => 'berlangsung']);

        $this->withToken($token)->getJson('/api/muk/penilaian?kelompok=perlu_tindakan')->assertJsonCount(0, 'data');
        $this->withToken($token)->getJson('/api/muk/penilaian?kelompok=berlangsung')->assertJsonCount(1, 'data');
    });

    it('filter status dan selesai', function () {
        $asesi = Asesi::factory()->create();
        MukFixture::penilaian(asesi: $asesi)->update(['status' => 'selesai_kompeten']);
        MukFixture::penilaian(asesi: $asesi);
        $token = MukFixture::token($asesi);

        $this->withToken($token)->getJson('/api/muk/penilaian?kelompok=selesai')->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson('/api/muk/penilaian?status=menunggu_pengisian_apl')->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson('/api/muk/penilaian?status=ngawur')->assertUnprocessable();
    });

    it('paginasi dibatasi maksimal 50 per halaman', function () {
        $asesor = Asesor::factory()->create();
        MukFixture::penilaian(asesor: $asesor);
        MukFixture::penilaian(asesor: $asesor);
        $token = MukFixture::token($asesor);

        $this->withToken($token)->getJson('/api/muk/penilaian?per_page=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.last_page', 2);
        $this->withToken($token)->getJson('/api/muk/penilaian?per_page=500')->assertUnprocessable();
    });
});

describe('akses', function () {
    it('tanpa token 401, token lspp-fe 403', function () {
        $asesi = Asesi::factory()->create();

        $this->getJson('/api/muk/penilaian')->assertUnauthorized();
        $this->withToken($asesi->createToken('auth_token')->plainTextToken)->getJson('/api/muk/penilaian')->assertForbidden();
    });

    it('tidak ada N+1: jumlah query tetap walau penilaian bertambah', function () {
        $asesor = Asesor::factory()->create();
        $token = MukFixture::token($asesor);
        MukFixture::penilaian(asesor: $asesor);

        $hitung = function () use ($token) {
            app('auth')->forgetGuards();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->withToken($token)->getJson('/api/muk/penilaian')->assertOk();

            // Abaikan query token Sanctum (update last_used_at tidak selalu terjadi).
            return collect(DB::getQueryLog())
                ->reject(fn ($q) => str_contains($q['query'], 'personal_access_tokens'))
                ->count();
        };

        $satu = $hitung();
        MukFixture::penilaian(asesor: $asesor);
        MukFixture::penilaian(asesor: $asesor);

        expect($hitung())->toBe($satu);
    });
});
