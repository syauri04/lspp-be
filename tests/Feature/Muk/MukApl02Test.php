<?php

use App\Models\Apl02BuktiRelevan;
use App\Models\Apl02Jawaban;
use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\ElemenKompetensi;
use App\Models\JenisDokumenMuk;
use App\Models\PenilaianMuk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MukFixture;

beforeEach(function () {
    Storage::fake('local');
});

function apl02Url(PenilaianMuk $p, string $akhiran = ''): string
{
    return "/api/muk/penilaian/{$p->id}/dokumen/apl-02{$akhiran}";
}

function elemenIds(PenilaianMuk $p): array
{
    return ElemenKompetensi::whereHas('unitKompetensi', fn ($q) => $q->where('skema_sertifikasi_id', $p->pendaftaran->skema_sertifikasi_id))
        ->orderBy('id')->pluck('id')->all();
}

/** Penilaian dengan APL.01 sudah dikirim (APL.02 terbuka). */
function siapkanApl02(?Asesor $asesor = null): array
{
    $asesi = Asesi::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi, asesor: $asesor);
    MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->update([
        'status' => DokumenPenilaian::STATUS_MENUNGGU_REVIEW,
        'data' => ['pribadi' => ['nama_lengkap' => 'Nama Dari APL01']],
    ]);

    return [$asesi, $p, MukFixture::token($asesi)];
}

function jawabSemua(PenilaianMuk $p, string $kompeten = 'K'): array
{
    return collect(elemenIds($p))->map(fn ($id) => [
        'elemen_id' => $id, 'kompeten' => $kompeten,
        'bukti' => $kompeten === 'K' ? [['id' => null, 'link' => "https://drive.example.com/bukti-{$id}"]] : [],
    ])->all();
}

function apl02BuktiUrl(PenilaianMuk $p, int $elemenId): string
{
    return apl02Url($p, "/elemen/{$elemenId}/bukti");
}

it('terkunci (403) sampai APL.01 dikirim', function () {
    $asesi = Asesi::factory()->create();
    $p = MukFixture::penilaian(asesi: $asesi);
    $token = MukFixture::token($asesi);

    $this->withToken($token)->getJson(apl02Url($p))->assertForbidden();
    $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => jawabSemua($p)])->assertForbidden();
});

it('GET menggabungkan master unit-elemen-KUK dengan jawaban, tanpa membuat baris di muka', function () {
    [, $p, $token] = siapkanApl02();

    $res = $this->withToken($token)->getJson(apl02Url($p))
        ->assertOk()
        ->assertJsonCount(2, 'data.unit_kompetensi')
        ->assertJsonCount(2, 'data.unit_kompetensi.0.elemen')
        ->assertJsonPath('data.unit_kompetensi.0.elemen.0.kuk.0.deskripsi', 'KUK 1.1.1')
        ->assertJsonPath('data.unit_kompetensi.0.elemen.0.jawaban.kompeten', null)
        ->assertJsonPath('data.progres', ['terisi' => 0, 'total' => 4])
        ->assertJsonPath('data.asesi.nama', 'Nama Dari APL01')
        ->assertJsonPath('data.skema.jenis', 'KKNI');

    expect(Apl02Jawaban::count())->toBe(0);
});

it('PATCH menyimpan K/BK + link bukti per elemen dan menghitung progres', function () {
    [, $p, $token] = siapkanApl02();
    [$e1, $e2] = elemenIds($p);

    $res = $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => [
        ['elemen_id' => $e1, 'kompeten' => 'K', 'bukti' => [
            ['id' => null, 'link' => 'https://drive.example.com/a'],
            ['id' => null, 'link' => null], // baris kosong diabaikan
            ['id' => null, 'link' => 'https://drive.example.com/b'],
        ]],
        ['elemen_id' => $e2, 'kompeten' => 'BK', 'bukti' => []],
    ]])
        ->assertOk()
        ->assertJsonPath('data.dokumen.status', 'draft')
        ->assertJsonCount(2, 'data.unit_kompetensi.0.elemen.0.jawaban.bukti')
        ->assertJsonPath('data.unit_kompetensi.0.elemen.0.jawaban.bukti.1.link', 'https://drive.example.com/b')
        ->assertJsonPath('data.progres.terisi', 2);

    // Ubah link baris pertama, hapus baris kedua, jawaban per elemen tetap satu baris.
    $idA = $res->json('data.unit_kompetensi.0.elemen.0.jawaban.bukti.0.id');
    $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => [
        ['elemen_id' => $e1, 'kompeten' => 'K', 'bukti' => [['id' => $idA, 'link' => 'https://drive.example.com/a2']]],
    ]])
        ->assertJsonCount(1, 'data.unit_kompetensi.0.elemen.0.jawaban.bukti')
        ->assertJsonPath('data.unit_kompetensi.0.elemen.0.jawaban.bukti.0.link', 'https://drive.example.com/a2');

    expect(Apl02Jawaban::count())->toBe(2);
});

it('menolak link selain http/https dan id bukti yang bukan miliknya', function () {
    [, $p, $token] = siapkanApl02();
    $e1 = elemenIds($p)[0];

    $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => [
        ['elemen_id' => $e1, 'kompeten' => 'K', 'bukti' => [['id' => null, 'link' => 'javascript:alert(1)']]],
    ]])->assertJsonValidationErrors('jawaban.0.bukti.0.link');

    $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => [
        ['elemen_id' => $e1, 'kompeten' => 'K', 'bukti' => [['id' => 999999, 'link' => 'https://x.test']]],
    ]])->assertJsonValidationErrors('jawaban');
});

it('unggah berkas bukti: baris baru, ganti berkas, hapus berkas, dan lihat berkas', function () {
    [, $p, $token] = siapkanApl02();
    $e1 = elemenIds($p)[0];
    $kirim = fn (array $data) => $this->withToken($token)->post(apl02BuktiUrl($p, $e1), $data, ['Accept' => 'application/json']);

    $res = $kirim(['file' => UploadedFile::fake()->create('sertifikat.pdf', 100, 'application/pdf')])
        ->assertOk()
        ->assertJsonPath('data.unit_kompetensi.0.elemen.0.jawaban.bukti.0.file.nama', 'sertifikat.pdf')
        ->assertJsonPath('data.unit_kompetensi.0.elemen.0.jawaban.kompeten', null);
    $buktiId = $res->json('data.unit_kompetensi.0.elemen.0.jawaban.bukti.0.id');
    $lama = Apl02BuktiRelevan::find($buktiId)->path_file;
    Storage::disk('local')->assertExists($lama);

    // Tambah link ke baris yang sama, lalu ganti berkasnya.
    $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => [
        ['elemen_id' => $e1, 'kompeten' => 'K', 'bukti' => [['id' => $buktiId, 'link' => 'https://x.test/1']]],
    ]])->assertOk();
    $kirim(['file' => UploadedFile::fake()->image('foto.png'), 'bukti_id' => $buktiId])
        ->assertJsonPath('data.unit_kompetensi.0.elemen.0.jawaban.bukti.0.file.nama', 'foto.png')
        ->assertJsonPath('data.unit_kompetensi.0.elemen.0.jawaban.bukti.0.link', 'https://x.test/1');
    Storage::disk('local')->assertMissing($lama);

    $this->withToken($token)->get(apl02Url($p, "/bukti/{$buktiId}/file"))->assertOk();

    // Hapus berkas: baris tetap ada karena masih punya link.
    $this->withToken($token)->deleteJson(apl02Url($p, "/bukti/{$buktiId}/file"))
        ->assertOk()
        ->assertJsonPath('data.unit_kompetensi.0.elemen.0.jawaban.bukti.0.file', null)
        ->assertJsonPath('data.unit_kompetensi.0.elemen.0.jawaban.bukti.0.link', 'https://x.test/1');
    Storage::disk('local')->assertDirectoryEmpty("apl02/{$p->id}");
});

it('menolak berkas tidak valid, elemen skema lain, dan akses berkas oleh asesi lain', function () {
    [, $p, $token] = siapkanApl02();
    $e1 = elemenIds($p)[0];
    $elemenLain = elemenIds(MukFixture::penilaian())[0];

    $this->withToken($token)->post(apl02BuktiUrl($p, $e1), ['file' => UploadedFile::fake()->create('x.exe', 5)], ['Accept' => 'application/json'])
        ->assertJsonValidationErrors('file');
    $this->withToken($token)->post(apl02BuktiUrl($p, $elemenLain), ['file' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertNotFound();

    $res = $this->withToken($token)->post(apl02BuktiUrl($p, $e1), ['file' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')], ['Accept' => 'application/json']);
    $buktiId = $res->json('data.unit_kompetensi.0.elemen.0.jawaban.bukti.0.id');
    app('auth')->forgetGuards();
    $this->withToken(MukFixture::token(Asesi::factory()->create()))->get(apl02Url($p, "/bukti/{$buktiId}/file"))->assertNotFound();
});

it('menolak elemen milik skema lain, nilai K/BK tidak valid, dan key asing', function () {
    [, $p, $token] = siapkanApl02();
    $lain = MukFixture::penilaian();
    $elemenLain = elemenIds($lain)[0];

    $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => [['elemen_id' => $elemenLain, 'kompeten' => 'K', 'bukti' => []]]])
        ->assertJsonValidationErrors('jawaban.0.elemen_id');
    $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => [['elemen_id' => elemenIds($p)[0], 'kompeten' => 'X', 'bukti' => []]]])
        ->assertJsonValidationErrors('jawaban.0.kompeten');
    $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => [['elemen_id' => elemenIds($p)[0], 'kompeten' => 'K', 'bukti' => [], 'skor' => 9]]])
        ->assertUnprocessable();
});

it('submit menolak bila ada elemen belum lengkap (K tanpa bukti / belum dijawab)', function () {
    [, $p, $token] = siapkanApl02();
    [$e1, $e2] = elemenIds($p);
    $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => [
        ['elemen_id' => $e1, 'kompeten' => 'K', 'bukti' => []],
        ['elemen_id' => $e2, 'kompeten' => 'BK', 'bukti' => []],
    ]]);
    $this->withToken($token)->postJson(apl02Url($p, '/tanda-tangan'), ['tanda_tangan' => pngDataUrl()]);

    $this->withToken($token)->postJson(apl02Url($p, '/submit'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['jawaban', "jawaban.{$e1}"])
        ->assertJsonMissingValidationErrors("jawaban.{$e2}");
});

it('submit berhasil: menunggu_review, snapshot, penilaian ikut menunggu_review, lalu terkunci', function () {
    [, $p, $token] = siapkanApl02();
    $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => jawabSemua($p)])->assertOk();
    $this->withToken($token)->postJson(apl02Url($p, '/tanda-tangan'), ['tanda_tangan' => pngDataUrl()])->assertOk();

    $this->withToken($token)->postJson(apl02Url($p, '/submit'))
        ->assertOk()
        ->assertJsonPath('data.dokumen.status', 'menunggu_review')
        ->assertJsonPath('data.dokumen.dapat_diubah', false);

    expect($p->fresh()->status)->toBe('menunggu_review')
        ->and(MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->snapshot['unit_kompetensi'][0]['elemen'])->toHaveCount(2);

    $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => jawabSemua($p, 'BK')])->assertForbidden();
});

it('snapshot: perubahan elemen/KUK di CMS tidak mengubah APL.02 yang sudah dikirim', function () {
    [, $p, $token] = siapkanApl02();
    $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => jawabSemua($p)]);
    $this->withToken($token)->postJson(apl02Url($p, '/tanda-tangan'), ['tanda_tangan' => pngDataUrl()]);
    $this->withToken($token)->postJson(apl02Url($p, '/submit'))->assertOk();

    ElemenKompetensi::find(elemenIds($p)[0])->update(['nama_elemen' => 'DIUBAH ADMIN']);

    $this->withToken($token)->getJson(apl02Url($p))
        ->assertJsonPath('data.unit_kompetensi.0.elemen.0.nama', 'Elemen 1.1')
        ->assertJsonPath('data.unit_kompetensi.0.elemen.0.jawaban.kompeten', 'K');
});

it('asesor penilaian bisa membaca APL.02 setelah dikirim, tapi tidak bisa mengisi', function () {
    $asesor = Asesor::factory()->create();
    [, $p, $token] = siapkanApl02($asesor);
    $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => jawabSemua($p)]);
    $this->withToken($token)->postJson(apl02Url($p, '/tanda-tangan'), ['tanda_tangan' => pngDataUrl()]);
    $this->withToken($token)->postJson(apl02Url($p, '/submit'))->assertOk();
    app('auth')->forgetGuards();

    $tokenAsesor = MukFixture::token($asesor);
    $this->withToken($tokenAsesor)->getJson(apl02Url($p))
        ->assertOk()
        ->assertJsonPath('data.peninjau.nama', $asesor->nama)
        ->assertJsonPath('data.progres.terisi', 4);
    $this->withToken($tokenAsesor)->patchJson(apl02Url($p), ['jawaban' => jawabSemua($p)])->assertForbidden();
});

it('GET tidak N+1: jumlah query tetap walau elemen bertambah', function () {
    [, $p, $token] = siapkanApl02();

    $hitung = function () use ($token, $p) {
        app('auth')->forgetGuards();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withToken($token)->getJson(apl02Url($p))->assertOk();

        return collect(DB::getQueryLog())->reject(fn ($q) => str_contains($q['query'], 'personal_access_tokens'))->count();
    };

    $awal = $hitung();
    $unit = $p->pendaftaran->skemaSertifikasi->unitKompetensis()->first();
    foreach (range(3, 6) as $n) {
        $unit->elemenKompetensi()->create(['nomor_elemen' => $n, 'nama_elemen' => "Tambahan {$n}", 'urutan' => $n])
            ->kriteriaUnjukKerja()->create(['nomor_kuk' => "{$n}.1", 'deskripsi' => 'x', 'urutan' => 1]);
    }

    expect($hitung())->toBe($awal);
});

describe('bukti dari Lampiran FR.APL.01', function () {
    /** Lampiran APL.01 penilaian: 1 berkas, 1 link, 1 kosong (tidak boleh muncul di pilihan). */
    function lampiranApl01(PenilaianMuk $p): array
    {
        $apl01 = $p->dokumenPenilaian()->kode(JenisDokumenMuk::APL_01)->first();

        return [
            $apl01->buktiRelevan()->create(['nama' => 'Laporan kegiatan', 'path_file' => 'apl01/x/laporan.pdf', 'nama_file_asli' => 'laporan.pdf', 'mime_type' => 'application/pdf']),
            $apl01->buktiRelevan()->create(['nama' => 'Sertifikat pelatihan', 'link' => 'https://contoh.id/sertifikat']),
            $apl01->buktiRelevan()->create(['nama' => 'Butir kosong']),
        ];
    }

    it('GET menampilkan pilihan lampiran APL.01 yang berisi saja; kosong bila tidak ada', function () {
        [, $p, $token] = siapkanApl02();

        $this->withToken($token)->getJson(apl02Url($p))->assertOk()->assertJsonPath('data.lampiran_apl01', []);

        [$laporan, $sertifikat] = lampiranApl01($p);
        $this->withToken($token)->getJson(apl02Url($p))
            ->assertOk()
            ->assertJsonCount(2, 'data.lampiran_apl01')
            ->assertJsonPath('data.lampiran_apl01.0.id', $laporan->id)
            ->assertJsonPath('data.lampiran_apl01.1.nama', 'Sertifikat pelatihan');
    });

    it('PATCH memilih lampiran sebagai bukti; elemen K dengan lampiran saja bisa dikirim', function () {
        [, $p, $token] = siapkanApl02();
        [$laporan, $sertifikat] = lampiranApl01($p);

        $jawaban = collect(elemenIds($p))->map(fn ($id) => [
            'elemen_id' => $id, 'kompeten' => 'K',
            'bukti' => [['id' => null, 'link' => null, 'lampiran_id' => $laporan->id]],
        ])->all();

        $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => $jawaban])
            ->assertOk()
            ->assertJsonPath('data.unit_kompetensi.0.elemen.0.jawaban.bukti.0.lampiran.id', $laporan->id)
            ->assertJsonPath('data.unit_kompetensi.0.elemen.0.jawaban.bukti.0.lampiran.nama', 'Laporan kegiatan')
            ->assertJsonPath('data.unit_kompetensi.0.elemen.0.jawaban.bukti.0.link', null);

        expect(Apl02BuktiRelevan::whereNotNull('apl01_bukti_relevan_id')->count())->toBe(count($jawaban));

        $this->withToken($token)->postJson(apl02Url($p, '/tanda-tangan'), ['tanda_tangan' => pngDataUrl()])->assertOk();
        $this->withToken($token)->postJson(apl02Url($p, '/submit'))->assertOk()
            ->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_MENUNGGU_REVIEW);
    });

    it('menolak lampiran milik penilaian lain, lampiran kosong, dan lampiran ganda di satu elemen', function () {
        [, $p, $token] = siapkanApl02();
        [$laporan, , $kosong] = lampiranApl01($p);
        [, $lain] = siapkanApl02();
        [$milikLain] = lampiranApl01($lain);
        $e1 = elemenIds($p)[0];

        foreach ([$milikLain->id, $kosong->id] as $id) {
            $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => [
                ['elemen_id' => $e1, 'kompeten' => 'K', 'bukti' => [['id' => null, 'link' => null, 'lampiran_id' => $id]]],
            ]])->assertStatus(422)->assertJsonValidationErrors('jawaban');
        }

        $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => [
            ['elemen_id' => $e1, 'kompeten' => 'K', 'bukti' => [
                ['id' => null, 'link' => null, 'lampiran_id' => $laporan->id],
                ['id' => null, 'link' => null, 'lampiran_id' => $laporan->id],
            ]],
        ]])->assertStatus(422)->assertJsonValidationErrors('jawaban');

        expect(Apl02BuktiRelevan::count())->toBe(0);
    });

    it('lampiran APL.01 dihapus: rujukan menjadi kosong, elemen K kembali wajib bukti', function () {
        [, $p, $token] = siapkanApl02();
        [$laporan] = lampiranApl01($p);
        $e1 = elemenIds($p)[0];

        $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => [
            ['elemen_id' => $e1, 'kompeten' => 'K', 'bukti' => [['id' => null, 'link' => null, 'lampiran_id' => $laporan->id]]],
        ]])->assertOk();

        $laporan->delete();
        $bukti = Apl02BuktiRelevan::sole();
        expect($bukti->apl01_bukti_relevan_id)->toBeNull()->and($bukti->terisi())->toBeFalse();
    });
});

describe('kekurangan sebelum kirim (ditampilkan di form)', function () {
    it('tidak ada sebelum disimpan; setelah simpan berisi elemen yang belum lengkap & tanda tangan', function () {
        [, $p, $token] = siapkanApl02();
        [$e1, $e2] = elemenIds($p);

        $this->withToken($token)->getJson(apl02Url($p))->assertOk()->assertJsonMissingPath('data.dokumen.kekurangan');

        $res = $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => [
            ['elemen_id' => $e1, 'kompeten' => 'K', 'bukti' => []],
            ['elemen_id' => $e2, 'kompeten' => 'BK', 'bukti' => []],
        ]])->assertOk();

        expect($res->json('data.dokumen.kekurangan'))
            ->toHaveKey("jawaban.{$e1}", 'Unggah dokumen, sisipkan link, atau pilih lampiran FR.APL.01 minimal satu bukti.')
            ->not->toHaveKey("jawaban.{$e2}")
            ->toHaveKey('tanda_tangan', 'Tanda tangani dokumen sebelum mengirim.');
    });

    it('kosong ({}) saat sudah lengkap; tidak ada untuk asesor maupun setelah dikirim', function () {
        $asesor = Asesor::factory()->create();
        [, $p, $token] = siapkanApl02($asesor);
        $this->withToken($token)->patchJson(apl02Url($p), ['jawaban' => jawabSemua($p)]);
        $res = $this->withToken($token)->postJson(apl02Url($p, '/tanda-tangan'), ['tanda_tangan' => pngDataUrl()])->assertOk();
        expect($res->json('data.dokumen.kekurangan'))->toBe([]);

        app('auth')->forgetGuards();
        $this->withToken(MukFixture::token($asesor))->getJson(apl02Url($p))
            ->assertJsonMissingPath('data.dokumen.kekurangan');

        app('auth')->forgetGuards();
        $this->withToken($token)->postJson(apl02Url($p, '/submit'))->assertOk()
            ->assertJsonMissingPath('data.dokumen.kekurangan');
    });
});
