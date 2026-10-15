<?php

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\BuktiPersyaratanDasar;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\PenilaianMuk;
use App\Models\RiwayatDokumenPenilaian;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MukFixture;

beforeEach(function () {
    Storage::fake('local');
});

function apl01Url(PenilaianMuk $p, string $akhiran = ''): string
{
    return "/api/muk/penilaian/{$p->id}/dokumen/apl-01{$akhiran}";
}

function dataLengkap(): array
{
    return [
        'pribadi' => [
            'nama_lengkap' => 'Budi Santoso', 'nik' => '3171234567890001', 'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-05-17', 'jenis_kelamin' => 'laki_laki', 'kebangsaan' => 'Indonesia',
            'alamat_rumah' => 'Jl. Melati 1', 'hp' => '0812345678', 'email' => 'budi@contoh.test',
            'kualifikasi_pendidikan' => 'S1 Pariwisata',
        ],
        'tujuan_asesmen' => 'sertifikasi',
    ];
}

/** @return array{0: Asesi, 1: PenilaianMuk, 2: string} */
function siapkanApl01(): array
{
    $asesi = Asesi::factory()->create(['name' => 'Budi', 'phone' => '0811', 'address' => 'Alamat profil']);
    $p = MukFixture::penilaian(asesi: $asesi);

    return [$asesi, $p, MukFixture::token($asesi)];
}

function buktiPertama(PenilaianMuk $p): int
{
    return BuktiPersyaratanDasar::where('skema_sertifikasi_id', $p->pendaftaran->skema_sertifikasi_id)->value('id');
}

function unggahBukti($test, PenilaianMuk $p, string $token, ?int $buktiId = null, ?UploadedFile $file = null)
{
    $buktiId ??= buktiPertama($p);

    return $test->withToken($token)->post(apl01Url($p, "/bukti/{$buktiId}"), [
        'file' => $file ?? UploadedFile::fake()->create('ijazah.pdf', 200, 'application/pdf'),
    ], ['Accept' => 'application/json']);
}

function isiLengkapDanTtd($test, PenilaianMuk $p, string $token): void
{
    $test->withToken($token)->patchJson(apl01Url($p), ['isian' => dataLengkap()])->assertOk();
    unggahBukti($test, $p, $token)->assertOk();
    $test->withToken($token)->postJson(apl01Url($p, '/tanda-tangan'), ['tanda_tangan' => pngDataUrl()])->assertOk();
}

describe('GET payload', function () {
    it('memberi prefill dari profil asesi selama data belum disimpan, plus data master skema', function () {
        [, $p, $token] = siapkanApl01();

        $this->withToken($token)->getJson(apl01Url($p))
            ->assertOk()
            ->assertJsonPath('data.prefill', true)
            ->assertJsonPath('data.isian.pribadi.nama_lengkap', 'Budi')
            ->assertJsonPath('data.isian.pribadi.hp', '0811')
            ->assertJsonPath('data.dokumen.status', 'belum_diisi')
            ->assertJsonPath('data.dokumen.dapat_diubah', true)
            ->assertJsonPath('data.skema.judul', 'Skema Uji')
            ->assertJsonCount(2, 'data.unit_kompetensi')
            ->assertJsonCount(1, 'data.bukti_persyaratan')
            ->assertJsonPath('data.bukti_persyaratan.0.file', null)
            ->assertJsonCount(2, 'data.bukti_administratif')
            ->assertJsonMissingPath('data.dokumen.path_tanda_tangan_asesi');
    });

    it('asesi lain mendapat 404, slug tak terdaftar 404', function () {
        [, $p] = siapkanApl01();

        $this->withToken(MukFixture::token(Asesi::factory()->create()))->getJson(apl01Url($p))->assertNotFound();
        $this->withToken(MukFixture::token($p->pendaftaran->asesi))->getJson("/api/muk/penilaian/{$p->id}/dokumen/mapa-01")->assertNotFound();
    });

    it('asesor penilaian tidak bisa membaca APL.01 yang belum dikirim', function () {
        $asesor = Asesor::factory()->create();
        $p = MukFixture::penilaian(asesor: $asesor);

        $this->withToken(MukFixture::token($asesor))->getJson(apl01Url($p))->assertForbidden();
    });
});

describe('PATCH simpan draft', function () {
    it('menyimpan parsial, mengubah status ke draft, dan prefill tidak dipakai lagi', function () {
        [, $p, $token] = siapkanApl01();

        $this->withToken($token)->patchJson(apl01Url($p), ['isian' => ['pribadi' => ['nik' => '123ABC']]])
            ->assertOk()
            ->assertJsonPath('data.dokumen.status', 'draft')
            ->assertJsonPath('data.prefill', false)
            ->assertJsonPath('data.isian.pribadi.nik', '123ABC')
            ->assertJsonPath('data.isian.pribadi.nama_lengkap', 'Budi'); // dari prefill, ikut tersimpan

        // Simpan berikutnya hanya mengubah field yang dikirim.
        $this->withToken($token)->patchJson(apl01Url($p), ['isian' => ['pribadi' => ['kebangsaan' => 'WNA']]])
            ->assertJsonPath('data.isian.pribadi.nik', '123ABC')
            ->assertJsonPath('data.isian.pribadi.kebangsaan', 'WNA');

        expect(RiwayatDokumenPenilaian::where('aksi', 'mulai_diisi')->count())->toBe(1);
    });

    it('menolak key yang tidak diizinkan dan nilai tidak valid', function () {
        [, $p, $token] = siapkanApl01();

        $this->withToken($token)->patchJson(apl01Url($p), ['isian' => ['pribadi' => ['is_admin' => true]]])
            ->assertUnprocessable();
        $this->withToken($token)->patchJson(apl01Url($p), ['isian' => ['status' => 'selesai']])
            ->assertUnprocessable();
        $this->withToken($token)->patchJson(apl01Url($p), ['isian' => ['pribadi' => ['tanggal_lahir' => '2999-01-01']]])
            ->assertJsonValidationErrors('isian.pribadi.tanggal_lahir');

        expect(MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->data)->toBeNull();
    });

    it('menyimpan kualifikasi pendidikan di data pekerjaan', function () {
        [, $p, $token] = siapkanApl01();

        $this->withToken($token)->patchJson(apl01Url($p), ['isian' => ['pekerjaan' => ['kualifikasi_pendidikan' => 'D3']]])
            ->assertJsonPath('data.isian.pekerjaan.kualifikasi_pendidikan', 'D3');
    });
});

describe('unggah bukti persyaratan dasar', function () {
    it('menyimpan berkas di disk privat dan bisa dilihat pemilik', function () {
        [, $p, $token] = siapkanApl01();

        unggahBukti($this, $p, $token)
            ->assertOk()
            ->assertJsonPath('data.dokumen.status', 'draft')
            ->assertJsonPath('data.bukti_persyaratan.0.file.nama', 'ijazah.pdf')
            ->assertJsonMissingPath('data.bukti_persyaratan.0.path_file');

        $row = MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->verifikasiPersyaratan()->first();
        expect($row->path_file)->toStartWith("apl01/{$p->id}/bukti-");
        Storage::disk('local')->assertExists($row->path_file);

        $this->withToken($token)->get(apl01Url($p, '/bukti/'.buktiPertama($p)))->assertOk();
        app('auth')->forgetGuards();
        $this->withToken(MukFixture::token(Asesi::factory()->create()))->get(apl01Url($p, '/bukti/'.buktiPertama($p)))->assertNotFound();
    });

    it('mengganti berkas menghapus berkas lama; hapus mengosongkan', function () {
        [, $p, $token] = siapkanApl01();

        unggahBukti($this, $p, $token);
        $lama = MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->verifikasiPersyaratan()->first()->path_file;
        unggahBukti($this, $p, $token, file: UploadedFile::fake()->image('foto.png'))->assertJsonPath('data.bukti_persyaratan.0.file.nama', 'foto.png');

        Storage::disk('local')->assertMissing($lama);

        $this->withToken($token)->deleteJson(apl01Url($p, '/bukti/'.buktiPertama($p)))
            ->assertOk()
            ->assertJsonPath('data.bukti_persyaratan.0.file', null);
        Storage::disk('local')->assertDirectoryEmpty("apl01/{$p->id}");
    });

    it('menolak tipe berkas tidak diizinkan, terlalu besar, dan bukti milik skema lain', function () {
        [, $p, $token] = siapkanApl01();
        $buktiLain = BuktiPersyaratanDasar::create([
            'skema_sertifikasi_id' => MukFixture::skema()->id, 'deskripsi' => 'Lain', 'urutan' => 1,
        ]);

        unggahBukti($this, $p, $token, file: UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload'))
            ->assertJsonValidationErrors('file');
        unggahBukti($this, $p, $token, file: UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf'))
            ->assertJsonValidationErrors('file');
        unggahBukti($this, $p, $token, $buktiLain->id)->assertNotFound();
    });

    it('unggah setelah tanda tangan menghapus tanda tangan', function () {
        [, $p, $token] = siapkanApl01();
        $this->withToken($token)->postJson(apl01Url($p, '/tanda-tangan'), ['tanda_tangan' => pngDataUrl()]);

        unggahBukti($this, $p, $token)->assertJsonPath('data.dokumen.tanda_tangan.asesi.ada', false);
    });

    it('asesor tidak bisa mengisi APL.01 (403 dari middleware peran)', function () {
        $asesor = Asesor::factory()->create();
        $p = MukFixture::penilaian(asesor: $asesor);

        $this->withToken(MukFixture::token($asesor))->patchJson(apl01Url($p), ['isian' => dataLengkap()])->assertForbidden();
    });
});

describe('tanda tangan', function () {
    it('menyimpan PNG yang sudah di-encode ulang di disk privat dan bisa dilihat lewat endpoint', function () {
        [, $p, $token] = siapkanApl01();

        $this->withToken($token)->postJson(apl01Url($p, '/tanda-tangan'), ['tanda_tangan' => pngDataUrl()])
            ->assertOk()
            ->assertJsonPath('data.dokumen.tanda_tangan.asesi.ada', true);

        $path = MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->path_tanda_tangan_asesi;
        expect($path)->toStartWith("signatures/{$p->id}/apl-01/asesi-");
        Storage::disk('local')->assertExists($path);

        $this->withToken($token)->get(apl01Url($p, '/tanda-tangan/asesi'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        app('auth')->forgetGuards(); // ganti user dalam satu test
        $this->withToken(MukFixture::token(Asesi::factory()->create()))->get(apl01Url($p, '/tanda-tangan/asesi'))->assertNotFound();
    });

    it('menolak bukan PNG, PNG palsu, kosong, dan terlalu besar', function () {
        [, $p, $token] = siapkanApl01();
        $kirim = fn ($v) => $this->withToken($token)->postJson(apl01Url($p, '/tanda-tangan'), ['tanda_tangan' => $v]);

        $kirim('data:image/jpeg;base64,'.base64_encode('x'))->assertJsonValidationErrors('tanda_tangan');
        $kirim('data:image/png;base64,'.base64_encode('<?php echo 1; ?>'))->assertJsonValidationErrors('tanda_tangan');
        $kirim('data:image/png;base64,@@@bukan-base64')->assertJsonValidationErrors('tanda_tangan');
        $kirim(pngDataUrl(kosong: true))->assertJsonValidationErrors('tanda_tangan');
        $kirim(pngDataUrl(4000, 100))->assertJsonValidationErrors('tanda_tangan');

        Storage::disk('local')->assertDirectoryEmpty('/');
    });

    it('tanda tangan ulang menghapus file lama; isi berubah setelah ttd menghapus ttd', function () {
        [, $p, $token] = siapkanApl01();

        $this->withToken($token)->postJson(apl01Url($p, '/tanda-tangan'), ['tanda_tangan' => pngDataUrl()]);
        $lama = MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->path_tanda_tangan_asesi;
        $this->withToken($token)->postJson(apl01Url($p, '/tanda-tangan'), ['tanda_tangan' => pngDataUrl(400, 120)]);
        $baru = MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->path_tanda_tangan_asesi;

        Storage::disk('local')->assertMissing($lama);
        Storage::disk('local')->assertExists($baru);

        $this->withToken($token)->patchJson(apl01Url($p), ['isian' => ['pribadi' => ['nik' => '999']]])
            ->assertJsonPath('data.dokumen.tanda_tangan.asesi.ada', false);
        Storage::disk('local')->assertMissing($baru);

        // Simpan tanpa perubahan isi tidak menghapus tanda tangan.
        $this->withToken($token)->postJson(apl01Url($p, '/tanda-tangan'), ['tanda_tangan' => pngDataUrl()]);
        $this->withToken($token)->patchJson(apl01Url($p), ['isian' => ['pribadi' => ['nik' => '999']]])
            ->assertJsonPath('data.dokumen.tanda_tangan.asesi.ada', true);
    });
});

describe('submit', function () {
    it('menolak bila belum lengkap dengan error per field', function () {
        [, $p, $token] = siapkanApl01();
        $this->withToken($token)->patchJson(apl01Url($p), ['isian' => ['pribadi' => ['nik' => '1']]]);

        $this->withToken($token)->postJson(apl01Url($p, '/submit'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['isian.pribadi.tempat_lahir', 'isian.tujuan_asesmen', 'bukti_persyaratan', 'tanda_tangan']);
    });

    it('menolak submit dokumen yang belum pernah diisi', function () {
        [, $p, $token] = siapkanApl01();

        $this->withToken($token)->postJson(apl01Url($p, '/submit'))->assertStatus(409);
    });

    it('berhasil: status menunggu_review, snapshot tersimpan, riwayat tercatat, lalu terkunci', function () {
        [, $p, $token] = siapkanApl01();
        isiLengkapDanTtd($this, $p, $token);

        $this->withToken($token)->postJson(apl01Url($p, '/submit'))
            ->assertOk()
            ->assertJsonPath('data.dokumen.status', 'menunggu_review')
            ->assertJsonPath('data.dokumen.dapat_diubah', false);

        $dok = MukFixture::dokumen($p, JenisDokumenMuk::APL_01);
        expect($dok->submitted_at)->not->toBeNull()
            ->and($dok->snapshot['unit_kompetensi'])->toHaveCount(2)
            ->and(RiwayatDokumenPenilaian::where('aksi', 'submit')->where('dokumen_penilaian_id', $dok->id)->exists())->toBeTrue();

        // Terkunci: tidak bisa diedit, ttd ulang, atau submit ulang.
        $this->withToken($token)->patchJson(apl01Url($p), ['isian' => ['pribadi' => ['nik' => '2']]])->assertForbidden();
        $this->withToken($token)->postJson(apl01Url($p, '/tanda-tangan'), ['tanda_tangan' => pngDataUrl()])->assertForbidden();
        $this->withToken($token)->postJson(apl01Url($p, '/submit'))->assertForbidden();
        unggahBukti($this, $p, $token)->assertForbidden();

        // APL.02 terbuka; penilaian belum berubah karena APL.02 belum dikirim.
        expect($p->fresh()->status)->toBe('menunggu_pengisian_apl');
        $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}")
            ->assertJsonPath('data.dokumen.1.terkunci', false);
    });

    it('snapshot membekukan isi: edit skema di CMS tidak mengubah APL.01 yang sudah dikirim', function () {
        [, $p, $token] = siapkanApl01();
        isiLengkapDanTtd($this, $p, $token);
        $this->withToken($token)->postJson(apl01Url($p, '/submit'))->assertOk();

        $p->pendaftaran->skemaSertifikasi->unitKompetensis()->first()->update(['judul_unit' => 'DIUBAH ADMIN']);

        $this->withToken($token)->getJson(apl01Url($p))->assertJsonPath('data.unit_kompetensi.0.judul_unit', 'Unit 1');
    });

    it('asesor bisa membaca APL.01 setelah dikirim (read-only)', function () {
        $asesor = Asesor::factory()->create();
        $asesi = Asesi::factory()->create();
        $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);
        $token = MukFixture::token($asesi);
        isiLengkapDanTtd($this, $p, $token);
        $this->withToken($token)->postJson(apl01Url($p, '/submit'))->assertOk();

        $this->withToken(MukFixture::token($asesor))->getJson(apl01Url($p))
            ->assertOk()
            ->assertJsonPath('data.dokumen.dapat_diubah', false)
            ->assertJsonPath('data.isian.pribadi.nama_lengkap', 'Budi Santoso');
        $this->withToken(MukFixture::token($asesor))->get(apl01Url($p, '/tanda-tangan/asesi'))->assertOk();
    });

    it('status penilaian jadi menunggu_review saat kedua APL dikirim', function () {
        [, $p, $token] = siapkanApl01();
        isiLengkapDanTtd($this, $p, $token);
        MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->update(['status' => DokumenPenilaian::STATUS_MENUNGGU_REVIEW]);

        $this->withToken($token)->postJson(apl01Url($p, '/submit'))->assertOk();

        expect($p->fresh()->status)->toBe('menunggu_review');
    });
});
