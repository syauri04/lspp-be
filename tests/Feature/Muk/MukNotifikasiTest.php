<?php

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\BuktiPersyaratanDasar;
use App\Models\DokumenPenilaian;
use App\Models\ElemenKompetensi;
use App\Models\JenisDokumenMuk;
use App\Models\PenilaianMuk;
use App\Models\User;
use App\Notifications\JadwalAsesmenDitentukanNotifikasi;
use App\Notifications\JadwalAsesmenUntukAsesorNotifikasi;
use App\Notifications\Muk\DokumenDitinjauNotifikasi;
use App\Notifications\Muk\DokumenSiapDitinjauNotifikasi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Support\MukFixture;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
});

function lepasAuth(): void
{
    app('auth')->forgetGuards();
    app('auth')->shouldUse('web');
}

/** Asesi mengirim APL.01 (lengkap) lewat API. */
function kirimApl01($test, PenilaianMuk $p, string $token): void
{
    $url = "/api/muk/penilaian/{$p->id}/dokumen/apl-01";
    $buktiId = BuktiPersyaratanDasar::where('skema_sertifikasi_id', $p->pendaftaran->skema_sertifikasi_id)->value('id');
    $test->withToken($token)->patchJson($url, ['isian' => [
        'pribadi' => [
            'nama_lengkap' => 'Budi', 'nik' => '123', 'tempat_lahir' => 'Jakarta', 'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'laki_laki', 'kebangsaan' => 'Indonesia', 'alamat_rumah' => 'Jl. A',
            'hp' => '0812', 'email' => 'budi@contoh.test', 'kualifikasi_pendidikan' => 'S1',
        ],
        'tujuan_asesmen' => 'sertifikasi',
    ]])->assertOk();
    $test->withToken($token)->post("{$url}/bukti/{$buktiId}", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
    $test->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
    $test->withToken($token)->postJson("{$url}/submit")->assertOk();
    lepasAuth();
}

/** Asesi mengirim APL.02 (semua K + link) lewat API. */
function kirimApl02($test, PenilaianMuk $p, string $token): void
{
    $url = "/api/muk/penilaian/{$p->id}/dokumen/apl-02";
    $jawaban = ElemenKompetensi::whereHas('unitKompetensi', fn ($q) => $q->where('skema_sertifikasi_id', $p->pendaftaran->skema_sertifikasi_id))
        ->pluck('id')->map(fn ($id) => ['elemen_id' => $id, 'kompeten' => 'K', 'bukti' => [['id' => null, 'link' => 'https://x.test']]])->all();
    $test->withToken($token)->patchJson($url, ['jawaban' => $jawaban])->assertOk();
    $test->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
    $test->withToken($token)->postJson("{$url}/submit")->assertOk();
    lepasAuth();
}

function terimaApl01($test, PenilaianMuk $p): void
{
    $dok = MukFixture::dokumen($p, JenisDokumenMuk::APL_01);
    $buktiId = BuktiPersyaratanDasar::where('skema_sertifikasi_id', $p->pendaftaran->skema_sertifikasi_id)->value('id');
    $test->actingAs($test->admin)->post(route('review-apl01.update', $dok), [
        'hasil' => 'diterima', 'tanda_tangan' => pngDataUrl(),
        'persyaratan' => [$buktiId => 'memenuhi'], 'administratif' => ['ktp' => 'memenuhi', 'pas_foto' => 'memenuhi'],
    ])->assertSessionHasNoErrors();
    lepasAuth();
}

it('APL.01 dikirim -> admin diberi tahu (email + dashboard) dengan link review CMS', function () {
    $asesi = Asesi::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi);

    kirimApl01($this, $p, MukFixture::token($asesi));

    Notification::assertSentTo($this->admin, DokumenSiapDitinjauNotifikasi::class, function ($n, $channels) use ($p) {
        $mail = $n->toMail($this->admin);

        return $channels === ['mail', 'database']
            && $mail->actionUrl === route('review-apl01.show', MukFixture::dokumen($p, JenisDokumenMuk::APL_01));
    });
});

it('APL.01 dikembalikan / diterima -> asesi diberi tahu dengan catatan & deep link MUK', function () {
    $asesi = Asesi::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi);
    kirimApl01($this, $p, MukFixture::token($asesi));
    $dok = MukFixture::dokumen($p, JenisDokumenMuk::APL_01);
    $buktiId = BuktiPersyaratanDasar::where('skema_sertifikasi_id', $p->pendaftaran->skema_sertifikasi_id)->value('id');

    $this->actingAs($this->admin)->post(route('review-apl01.update', $dok), [
        'hasil' => 'ditolak', 'catatan' => 'KTP buram',
        'persyaratan' => [$buktiId => 'memenuhi'], 'administratif' => ['ktp' => 'tidak_memenuhi', 'pas_foto' => 'memenuhi'],
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($asesi, DokumenDitinjauNotifikasi::class, function ($n) use ($asesi, $p) {
        $mail = $n->toMail($asesi);

        return str_contains($mail->subject, 'Perlu Diperbaiki')
            && collect($mail->introLines)->contains('Catatan: KTP buram')
            && $mail->actionUrl === config('muk.frontend_url').'/penilaian/'.$p->id;
    });
});

it('asesor baru diberi tahu APL.02 siap ditinjau setelah APL.01 diterima admin', function () {
    $asesi = Asesi::factory()->create();
    $asesor = Asesor::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);
    $token = MukFixture::token($asesi);

    kirimApl01($this, $p, $token);
    kirimApl02($this, $p, $token);
    Notification::assertNotSentTo($asesor, DokumenSiapDitinjauNotifikasi::class);

    terimaApl01($this, $p);
    Notification::assertSentToTimes($asesor, DokumenSiapDitinjauNotifikasi::class, 1);
});

it('APL.02 dikirim setelah APL.01 diterima -> asesor langsung diberi tahu; hasil review asesor -> asesi', function () {
    $asesi = Asesi::factory()->create();
    $asesor = Asesor::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);
    $token = MukFixture::token($asesi);

    kirimApl01($this, $p, $token);
    terimaApl01($this, $p);
    kirimApl02($this, $p, $token);
    Notification::assertSentToTimes($asesor, DokumenSiapDitinjauNotifikasi::class, 1);

    $this->withToken(MukFixture::token($asesor))
        ->postJson("/api/muk/penilaian/{$p->id}/dokumen/apl-02/review", ['hasil' => 'dapat_dilanjutkan', 'tanda_tangan' => pngDataUrl()])
        ->assertOk();

    Notification::assertSentTo($asesi, DokumenDitinjauNotifikasi::class, fn ($n) => str_contains($n->toMail($asesi)->subject, 'FR.APL.02'));
});

it('email jadwal memuat deep link /penilaian/{id} untuk asesi & asesor', function () {
    $asesi = Asesi::factory()->create();
    $asesor = Asesor::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);
    $jadwal = $p->jadwalAsesmen;
    $harapan = config('muk.frontend_url').'/penilaian/'.$p->id;

    expect((new JadwalAsesmenDitentukanNotifikasi($jadwal))->toMail($asesi)->actionUrl)->toBe($harapan)
        ->and((new JadwalAsesmenUntukAsesorNotifikasi($jadwal))->toMail($asesor)->actionUrl)->toBe($harapan);
});

it('berkas bukti gambar dikirim dengan CSP sandbox, PDF tanpa sandbox', function () {
    $asesi = Asesi::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi);
    $token = MukFixture::token($asesi);
    $url = "/api/muk/penilaian/{$p->id}/dokumen/apl-01";
    $buktiId = BuktiPersyaratanDasar::where('skema_sertifikasi_id', $p->pendaftaran->skema_sertifikasi_id)->value('id');

    $this->withToken($token)->post("{$url}/bukti/{$buktiId}", ['file' => UploadedFile::fake()->image('foto.png')], ['Accept' => 'application/json'])->assertOk();
    $this->withToken($token)->get("{$url}/bukti/{$buktiId}")->assertHeader('Content-Security-Policy', 'sandbox');

    $this->withToken($token)->post("{$url}/bukti/{$buktiId}", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
    $this->withToken($token)->get("{$url}/bukti/{$buktiId}")->assertHeaderMissing('Content-Security-Policy');

    expect(DokumenPenilaian::count())->toBeGreaterThan(0);
});
