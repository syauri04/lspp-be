<?php

use App\Models\Apl01VerifikasiPersyaratan;
use App\Models\Asesi;
use App\Models\BuktiPersyaratanDasar;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\PenilaianMuk;
use App\Models\RiwayatDokumenPenilaian;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MukFixture;

/** Ganti user dalam satu test: lepas user guard sebelumnya & kembalikan guard default. */
function resetAuth(): void
{
    app('auth')->forgetGuards();
    app('auth')->shouldUse('web');
}

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->create();
});

/** Asesi mengisi lengkap, unggah bukti, tanda tangan, dan mengirim APL.01 lewat API MUK. */
function apl01Terkirim($test): array
{
    $asesi = Asesi::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi);
    $token = MukFixture::token($asesi);
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
    $test->withToken($token)->post("{$url}/bukti/{$buktiId}", ['file' => UploadedFile::fake()->create('ijazah.pdf', 50, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
    $test->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
    $test->withToken($token)->postJson("{$url}/submit")->assertOk();
    resetAuth();

    return [$p, MukFixture::dokumen($p, JenisDokumenMuk::APL_01), $buktiId, $token];
}

function formReview(int $buktiId, string $hasil, array $tambahan = []): array
{
    return [
        'hasil' => $hasil,
        'persyaratan' => [$buktiId => 'memenuhi'],
        'administratif' => ['ktp' => 'memenuhi', 'pas_foto' => 'memenuhi'],
        ...$tambahan,
    ];
}

it('daftar menampilkan APL.01 yang menunggu review dan detail bisa dibuka', function () {
    [, $dok] = apl01Terkirim($this);

    $this->actingAs($this->admin)->get(route('review-apl01.index'))->assertOk()->assertSee('Menunggu Review');
    $this->actingAs($this->admin)->get(route('review-apl01.show', $dok))->assertOk()->assertSee('ijazah.pdf');
    $this->actingAs($this->admin)->get(route('review-apl01.bukti', [$dok, BuktiPersyaratanDasar::value('id')]))->assertOk();
});

it('detail menampilkan lampiran bukti relevan yang berisi berkas/link; berkas bisa dibuka', function () {
    [, $dok] = apl01Terkirim($this);
    [, $lain] = apl01Terkirim($this);
    $this->actingAs($this->admin)->get(route('review-apl01.show', $dok))->assertOk()->assertDontSee('3. Lampiran Bukti yang relevan');

    Storage::disk('local')->put('apl01/x/laporan.pdf', '%PDF-1.4');
    $laporan = $dok->buktiRelevan()->create(['kunci' => sha1('Laporan'), 'nama' => 'Laporan kegiatan', 'path_file' => 'apl01/x/laporan.pdf', 'nama_file_asli' => 'laporan.pdf', 'mime_type' => 'application/pdf']);
    $dok->buktiRelevan()->create(['nama' => 'Sertifikat pelatihan', 'link' => 'https://contoh.id/sertifikat']);
    $dok->buktiRelevan()->create(['kunci' => sha1('Kosong'), 'nama' => 'Butir kosong']);

    $this->actingAs($this->admin)->get(route('review-apl01.show', $dok))->assertOk()
        ->assertSee('3. Lampiran Bukti yang relevan')
        ->assertSee('laporan.pdf')
        ->assertSee('https://contoh.id/sertifikat')
        ->assertSee('Tambahan asesi')
        ->assertDontSee('Butir kosong');
    $this->actingAs($this->admin)->get(route('review-apl01.relevan', [$dok, $laporan->id]))->assertOk();

    // Lampiran dokumen lain -> 404.
    $this->actingAs($this->admin)->get(route('review-apl01.relevan', [$lain, $laporan->id]))->assertNotFound();
});

it('menolak akses tanpa login dan dokumen yang bukan APL.01', function () {
    [$p, $dok, , $token] = apl01Terkirim($this);

    $this->get(route('review-apl01.show', $dok))->assertRedirect();
    // Token Bearer asesi tidak berlaku di CMS (guard web/session).
    $this->withToken($token)->get(route('review-apl01.show', $dok))->assertRedirect();
    resetAuth();
    $this->actingAs($this->admin)->get(route('review-apl01.show', MukFixture::dokumen($p, JenisDokumenMuk::APL_02)))->assertNotFound();
});

it('diterima: verifikasi tersimpan, ttd admin, status selesai, riwayat tercatat', function () {
    [, $dok, $buktiId, $token] = apl01Terkirim($this);

    $this->actingAs($this->admin)
        ->post(route('review-apl01.update', $dok), formReview($buktiId, 'diterima', ['tanda_tangan' => pngDataUrl(), 'catatan' => 'Lengkap']))
        ->assertRedirect(route('review-apl01.show', $dok))
        ->assertSessionHasNoErrors();

    $dok->refresh();
    expect($dok->status)->toBe('selesai')
        ->and($dok->hasil_rekomendasi_admin)->toBe('diterima')
        ->and($dok->direview_oleh_user_id)->toBe($this->admin->id)
        ->and($dok->data_review['bukti_administratif']['ktp'])->toBe('memenuhi')
        ->and(Apl01VerifikasiPersyaratan::where('bukti_persyaratan_dasar_id', $buktiId)->value('memenuhi_syarat'))->toBeTrue()
        ->and(RiwayatDokumenPenilaian::where('aksi', 'review_admin_diterima')->exists())->toBeTrue();
    Storage::disk('local')->assertExists($dok->path_tanda_tangan_admin);

    resetAuth();
    // Asesi melihat hasil verifikasi & tanda tangan admin.
    $this->withToken($token)->getJson("/api/muk/penilaian/{$dok->penilaian_muk_id}/dokumen/apl-01")
        ->assertJsonPath('data.dokumen.hasil_rekomendasi_admin', 'diterima')
        ->assertJsonPath('data.dokumen.tanda_tangan.admin.ada', true)
        ->assertJsonPath('data.bukti_persyaratan.0.memenuhi_syarat', true)
        ->assertJsonPath('data.bukti_administratif.0.verifikasi', 'memenuhi');

    // Tidak bisa direview dua kali.
    $this->actingAs($this->admin)->post(route('review-apl01.update', $dok), formReview($buktiId, 'diterima', ['tanda_tangan' => pngDataUrl()]))
        ->assertStatus(409);
});

it('diterima wajib tanda tangan; ditolak wajib catatan; semua butir wajib diverifikasi', function () {
    [, $dok, $buktiId] = apl01Terkirim($this);

    $this->actingAs($this->admin)->post(route('review-apl01.update', $dok), formReview($buktiId, 'diterima'))
        ->assertSessionHasErrors('tanda_tangan');
    $this->actingAs($this->admin)->post(route('review-apl01.update', $dok), formReview($buktiId, 'ditolak'))
        ->assertSessionHasErrors('catatan');
    $this->actingAs($this->admin)->post(route('review-apl01.update', $dok), [
        'hasil' => 'ditolak', 'catatan' => 'x', 'persyaratan' => [$buktiId => 'memenuhi'], 'administratif' => ['ktp' => 'memenuhi'],
    ])->assertSessionHasErrors('persyaratan');

    expect($dok->fresh()->status)->toBe('menunggu_review');
});

it('ditolak: kembali ke draft, ttd asesi dihapus, asesi bisa revisi & kirim ulang, APL.02 tetap terbuka', function () {
    [$p, $dok, $buktiId, $token] = apl01Terkirim($this);
    $ttdAsesi = $dok->path_tanda_tangan_asesi;

    $this->actingAs($this->admin)
        ->post(route('review-apl01.update', $dok), [
            ...formReview($buktiId, 'ditolak', ['catatan' => 'Ijazah tidak terbaca, unggah ulang.']),
            'persyaratan' => [$buktiId => 'tidak_memenuhi'],
        ])
        ->assertSessionHasNoErrors();

    $dok->refresh();
    expect($dok->status)->toBe('draft')
        ->and($dok->hasil_rekomendasi_admin)->toBe('ditolak')
        ->and($dok->path_tanda_tangan_asesi)->toBeNull()
        ->and($dok->snapshot)->toBeNull();
    Storage::disk('local')->assertMissing($ttdAsesi);

    resetAuth();
    $url = "/api/muk/penilaian/{$p->id}/dokumen/apl-01";
    $this->withToken($token)->getJson($url)
        ->assertJsonPath('data.dokumen.dapat_diubah', true)
        ->assertJsonPath('data.dokumen.catatan_admin', 'Ijazah tidak terbaca, unggah ulang.')
        ->assertJsonPath('data.bukti_persyaratan.0.memenuhi_syarat', false);
    $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}")
        ->assertJsonPath('data.dokumen.1.terkunci', false);

    // Revisi & kirim ulang -> keputusan admin putaran lalu dikosongkan.
    $this->withToken($token)->post("{$url}/bukti/{$buktiId}", ['file' => UploadedFile::fake()->create('ijazah-baru.pdf', 50, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
    $this->withToken($token)->postJson("{$url}/tanda-tangan", ['tanda_tangan' => pngDataUrl()])->assertOk();
    $this->withToken($token)->postJson("{$url}/submit")->assertOk();

    expect($dok->fresh()->status)->toBe('menunggu_review')
        ->and($dok->fresh()->hasil_rekomendasi_admin)->toBeNull();
});

it('status penilaian kembali ke menunggu_pengisian_apl bila APL.01 dikembalikan', function () {
    [$p, $dok, $buktiId] = apl01Terkirim($this);
    MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->update(['status' => DokumenPenilaian::STATUS_MENUNGGU_REVIEW]);
    $p->update(['status' => PenilaianMuk::STATUS_MENUNGGU_REVIEW]);

    $this->actingAs($this->admin)->post(route('review-apl01.update', $dok), formReview($buktiId, 'ditolak', ['catatan' => 'Revisi']));

    expect($p->fresh()->status)->toBe('menunggu_pengisian_apl');
});
