<?php

use App\Models\Asesi;
use App\Models\PenerbitanSertifikasi;
use App\Models\PenilaianMuk;
use App\Models\Sertifikat;
use App\Models\User;
use App\Notifications\StatusPendaftaranNotifikasi;
use App\Services\Muk\SinkronStatusAsesmen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Support\MukFixture;

beforeEach(function () {
    Storage::fake('local');
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super-admin');
});

/** Penilaian MUK selesai (K/BK) -> antrean penerbitan dibuat oleh SinkronStatusAsesmen. */
function pnbAsesmenSelesai(string $hasil = 'kompeten'): PenerbitanSertifikasi
{
    $p = MukFixture::penilaian();
    $p->update(['status' => $hasil === 'kompeten' ? PenilaianMuk::STATUS_SELESAI_KOMPETEN : PenilaianMuk::STATUS_SELESAI_BELUM_KOMPETEN, 'selesai_at' => now()]);
    DB::transaction(fn () => app(SinkronStatusAsesmen::class)->selesai($p->fresh()));

    return PenerbitanSertifikasi::where('pendaftaran_sertifikasi_id', $p->pendaftaran_sertifikasi_id)->firstOrFail();
}

function pnbPdf(string $nama = 'sk.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nama, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function pnbUrl(PenerbitanSertifikasi $p, string $akhiran = ''): string
{
    return "/penerbitan-sertifikat/{$p->id}{$akhiran}";
}

function pnbUnggahSemuaSk($test, PenerbitanSertifikasi $p): void
{
    foreach (array_keys(config('sertifikasi.dokumen')) as $jenis) {
        $test->post(pnbUrl($p, "/dokumen/{$jenis}"), ['file' => pnbPdf("{$jenis}.pdf")])->assertRedirect()->assertSessionHasNoErrors();
    }
}

function pnbDataSertifikat(array $ubah = []): array
{
    return [
        'nama_pemegang' => 'Syaamil Fathan Alatas',
        'no_blanko' => '00119316',
        'no_sertifikat' => '5113.S93.3.2.3375.00002.3.2630',
        'tanggal_aktif' => '2026-08-28',
        'tanggal_expired' => '2029-08-28',
        ...$ubah,
    ];
}

function pnbTokenWebsite($asesi): string
{
    return $asesi->createToken('auth_token')->plainTextToken;
}

it('MUK selesai membuat antrean penerbitan (idempoten) dengan hasil dari MUK', function () {
    $p = pnbAsesmenSelesai('belum_kompeten');

    expect($p->hasil)->toBe('belum_kompeten')
        ->and($p->status)->toBe(PenerbitanSertifikasi::MENUNGGU_DOKUMEN)
        ->and($p->pendaftaran->status)->toBe('asesmen_selesai');

    DB::transaction(fn () => app(SinkronStatusAsesmen::class)->selesai($p->penilaianMuk));
    expect(PenerbitanSertifikasi::count())->toBe(1);
});

it('admin biasa hanya dapat melihat; mengubah ditolak (403)', function () {
    $p = pnbAsesmenSelesai();
    $this->actingAs(User::factory()->create());

    $this->get(pnbUrl($p))->assertOk()->assertSee('hanya dapat dilakukan oleh super admin');
    $this->post(pnbUrl($p, '/dokumen/sk_hasil_uji'), ['file' => pnbPdf()])->assertForbidden();
    $this->post(pnbUrl($p, '/sertifikat'), [...pnbDataSertifikat(), 'file' => pnbPdf()])->assertForbidden();
    $this->post(pnbUrl($p, '/selesaikan'))->assertForbidden();
    expect($p->dokumen()->count())->toBe(0);
});

it('menolak berkas bukan PDF (isi), jenis tidak dikenal, dan ukuran berlebih', function () {
    $p = pnbAsesmenSelesai();
    $this->actingAs($this->superAdmin);

    $palsu = UploadedFile::fake()->createWithContent('sk.pdf', 'bukan pdf <script>');
    $this->post(pnbUrl($p, '/dokumen/sk_hasil_uji'), ['file' => $palsu])->assertSessionHasErrors('file');
    $this->post(pnbUrl($p, '/dokumen/tidak_ada'), ['file' => pnbPdf()])->assertNotFound();
    $besar = UploadedFile::fake()->create('besar.pdf', config('sertifikasi.max_kb') + 1, 'application/pdf');
    $this->post(pnbUrl($p, '/dokumen/sk_hasil_uji'), ['file' => $besar])->assertSessionHasErrors('file');

    expect($p->dokumen()->count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('alur Kompeten: 4 SK + e-sertifikat -> selesai, pendaftaran completed, asesi dikabari', function () {
    Notification::fake();
    $p = pnbAsesmenSelesai();
    $this->actingAs($this->superAdmin);

    $this->post(pnbUrl($p, '/selesaikan'))->assertSessionHasErrors('selesaikan');

    pnbUnggahSemuaSk($this, $p);
    expect($p->fresh()->status)->toBe(PenerbitanSertifikasi::MENUNGGU_DOKUMEN); // e-sertifikat belum

    $this->post(pnbUrl($p, '/sertifikat'), pnbDataSertifikat())->assertSessionHasErrors('file');
    $this->post(pnbUrl($p, '/sertifikat'), [...pnbDataSertifikat(), 'file' => pnbPdf('sertifikat.pdf')])->assertSessionHasNoErrors();
    expect($p->fresh()->status)->toBe(PenerbitanSertifikasi::SIAP_DISELESAIKAN);

    $s = $p->sertifikat()->first();
    expect($s->no_sertifikat_normal)->toBe('5113.S93.3.2.3375.00002.3.2630')
        ->and($s->unit_kompetensi)->toHaveCount(2)
        ->and($s->skema_judul)->toBeArray();

    $this->post(pnbUrl($p, '/selesaikan'))->assertRedirect()->assertSessionHasNoErrors();
    $p->refresh();
    expect($p->status)->toBe(PenerbitanSertifikasi::SELESAI)
        ->and($p->diselesaikan_oleh)->toBe($this->superAdmin->id)
        ->and($p->pendaftaran->status)->toBe('completed')
        ->and($p->riwayat()->pluck('aksi')->all())->toContain('diselesaikan', 'sertifikat_diterbitkan', 'dokumen_diunggah');

    Notification::assertSentTo($p->pendaftaran->asesi, StatusPendaftaranNotifikasi::class, fn ($n) => $n->status === 'completed');

    // Halaman CMS (super admin, dengan form koreksi) & daftar dapat dirender.
    $this->get(pnbUrl($p))->assertOk()->assertSee('Simpan Perubahan')->assertSee('Alasan perubahan')->assertSee('5113.S93.3.2.3375.00002.3.2630');
    $this->get('/penerbitan-sertifikat?status=selesai')->assertOk()->assertSee('5113.S93.3.2.3375.00002.3.2630');
    $this->get('/penerbitan-sertifikat?q=00119316&status=semua')->assertOk()->assertSee('5113.S93.3.2.3375.00002.3.2630');

    // Tidak bisa diselesaikan dua kali; koreksi setelah selesai wajib beralasan.
    $this->post(pnbUrl($p, '/selesaikan'))->assertStatus(409);
    $this->post(pnbUrl($p, '/dokumen/sk_hasil_uji'), ['file' => pnbPdf()])->assertSessionHasErrors('alasan');
    $this->post(pnbUrl($p, '/dokumen/sk_hasil_uji'), ['file' => pnbPdf(), 'alasan' => 'Salah unggah'])->assertSessionHasNoErrors();
    expect(Storage::disk('local')->allFiles())->toHaveCount(5); // berkas lama dihapus
});

it('alur Belum Kompeten: cukup SK 1-4; e-sertifikat ditolak', function () {
    $p = pnbAsesmenSelesai('belum_kompeten');
    $this->actingAs($this->superAdmin);

    $this->post(pnbUrl($p, '/sertifikat'), [...pnbDataSertifikat(), 'file' => pnbPdf()])->assertSessionHasErrors('sertifikat');
    pnbUnggahSemuaSk($this, $p);
    expect($p->fresh()->status)->toBe(PenerbitanSertifikasi::SIAP_DISELESAIKAN);

    $this->post(pnbUrl($p, '/selesaikan'))->assertSessionHasNoErrors();
    expect($p->fresh()->pendaftaran->status)->toBe('completed');
});

it('nomor blanko & sertifikat unik (setelah normalisasi) dan tanggal kedaluwarsa setelah tanggal aktif', function () {
    $a = pnbAsesmenSelesai();
    $b = pnbAsesmenSelesai();
    $this->actingAs($this->superAdmin);

    $this->post(pnbUrl($a, '/sertifikat'), [...pnbDataSertifikat(), 'file' => pnbPdf()])->assertSessionHasNoErrors();
    $this->post(pnbUrl($b, '/sertifikat'), [...pnbDataSertifikat(['no_blanko' => ' 00119316 ', 'no_sertifikat' => 'lain-1']), 'file' => pnbPdf()])
        ->assertSessionHasErrors('no_blanko');
    $this->post(pnbUrl($b, '/sertifikat'), [...pnbDataSertifikat(['no_blanko' => '999', 'no_sertifikat' => '5113.s93.3.2.3375.00002.3.2630']), 'file' => pnbPdf()])
        ->assertSessionHasErrors('no_sertifikat');
    $this->post(pnbUrl($b, '/sertifikat'), [...pnbDataSertifikat(['no_blanko' => '999', 'no_sertifikat' => 'X', 'tanggal_expired' => '2026-01-01']), 'file' => pnbPdf()])
        ->assertSessionHasErrors('tanggal_expired');

    expect(Sertifikat::count())->toBe(1);
});

describe('asesi (profil lspp-fe)', function () {
    it('melihat & mengunduh hanya SK hasil uji dan e-sertifikat, setelah proses selesai', function () {
        $p = pnbAsesmenSelesai();
        $this->actingAs($this->superAdmin);
        pnbUnggahSemuaSk($this, $p);
        $this->post(pnbUrl($p, '/sertifikat'), [...pnbDataSertifikat(), 'file' => pnbPdf()]);

        $asesi = $p->pendaftaran->asesi;
        $kode = $p->pendaftaran->kode_pendaftaran;
        $token = pnbTokenWebsite($asesi);
        app('auth')->forgetGuards();

        // Belum diselesaikan: tidak terlihat & tidak dapat diunduh.
        $this->withToken($token)->getJson("/api/pendaftaran-sertifikasi/{$kode}")
            ->assertOk()->assertJsonPath('data.sertifikasi', null)
            ->assertJsonPath('data.kelompok', 'diproses')
            ->assertJsonPath('data.status_label', 'Menunggu penerbitan sertifikat');
        $this->withToken($token)->get("/api/pendaftaran-sertifikasi/{$kode}/sertifikasi/e-sertifikat")->assertNotFound();

        app('auth')->forgetGuards();
        $this->actingAs($this->superAdmin)->post(pnbUrl($p, '/selesaikan'));
        app('auth')->forgetGuards();

        $res = $this->withToken($token)->getJson("/api/pendaftaran-sertifikasi/{$kode}")->assertOk()
            ->assertJsonPath('data.kelompok', 'selesai')
            ->assertJsonPath('data.sertifikasi.hasil', 'kompeten')
            ->assertJsonCount(1, 'data.sertifikasi.dokumen')
            ->assertJsonPath('data.sertifikasi.dokumen.0.jenis', 'sk_hasil_uji')
            ->assertJsonPath('data.sertifikasi.sertifikat.no_blanko', '00119316')
            ->assertJsonPath('data.sertifikasi.sertifikat.status', 'berlaku');
        expect($res->getContent())->not->toContain('path_file')->not->toContain('sertifikasi/'.$p->id.'/');

        $this->withToken($token)->get("/api/pendaftaran-sertifikasi/{$kode}/sertifikasi/e-sertifikat")
            ->assertOk()->assertHeader('content-type', 'application/pdf')->assertHeader('x-content-type-options', 'nosniff');
        $this->withToken($token)->get("/api/pendaftaran-sertifikasi/{$kode}/sertifikasi/sk_hasil_uji")->assertOk();
        foreach (['sk_komite_teknis', 'surat_tugas_komite', 'berita_acara_pleno', 'tidak_ada'] as $internal) {
            $this->withToken($token)->get("/api/pendaftaran-sertifikasi/{$kode}/sertifikasi/{$internal}")->assertNotFound();
        }

        // Asesi lain ditolak.
        app('auth')->forgetGuards();
        $lain = pnbTokenWebsite(Asesi::factory()->create());
        $this->withToken($lain)->get("/api/pendaftaran-sertifikasi/{$kode}/sertifikasi/e-sertifikat")->assertForbidden();
    });
});

describe('verifikasi publik', function () {
    beforeEach(function () {
        $this->p = pnbAsesmenSelesai();
        $this->actingAs($this->superAdmin);
        pnbUnggahSemuaSk($this, $this->p);
        $this->post(pnbUrl($this->p, '/sertifikat'), [...pnbDataSertifikat(['tanggal_aktif' => now()->subYear()->toDateString(), 'tanggal_expired' => now()->addYears(2)->toDateString()]), 'file' => pnbPdf()]);
        app('auth')->forgetGuards();
        app('auth')->shouldUse('web');
    });

    it('tidak ditemukan sebelum proses selesai', function () {
        $this->getJson('/api/sertifikat/verifikasi?nomor=00119316')->assertNotFound();
    });

    it('cocok persis no. blanko / no. sertifikat; hanya data publik; tanpa pencarian sebagian', function () {
        $this->actingAs($this->superAdmin)->post(pnbUrl($this->p, '/selesaikan'));
        app('auth')->forgetGuards();

        $res = $this->getJson('/api/sertifikat/verifikasi?nomor='.urlencode(' 00119316 '))->assertOk()
            ->assertJsonPath('data.nama', 'Syaamil Fathan Alatas')
            ->assertJsonPath('data.masa_berlaku_tahun', 3)
            ->assertJsonPath('data.status', 'berlaku')
            ->assertJsonCount(2, 'data.unit_kompetensi');
        expect(array_keys($res->json('data')))->toEqualCanonicalizing(['nama', 'no_blanko', 'no_sertifikat', 'skema', 'unit_kompetensi', 'tanggal_aktif', 'tanggal_expired', 'masa_berlaku_tahun', 'status']);

        $this->getJson('/api/sertifikat/verifikasi?nomor=5113.s93.3.2.3375.00002.3.2630')->assertOk();
        $this->getJson('/api/sertifikat/verifikasi?nomor=0011')->assertNotFound();
        $this->getJson('/api/sertifikat/verifikasi?nomor=%25')->assertNotFound();
        $this->getJson('/api/sertifikat/verifikasi')->assertUnprocessable();
    });

    it('status tidak berlaku bila dicabut atau sudah lewat masa berlaku', function () {
        $this->actingAs($this->superAdmin)->post(pnbUrl($this->p, '/selesaikan'));
        $this->post(pnbUrl($this->p, '/sertifikat/status'), ['status' => 'tidak_berlaku'])->assertSessionHasErrors('alasan');
        $this->post(pnbUrl($this->p, '/sertifikat/status'), ['status' => 'tidak_berlaku', 'alasan' => 'Dicabut BNSP'])->assertSessionHasNoErrors();
        app('auth')->forgetGuards();
        $this->getJson('/api/sertifikat/verifikasi?nomor=00119316')->assertJsonPath('data.status', 'tidak_berlaku');

        $this->p->sertifikat()->update(['status' => 'berlaku', 'tanggal_expired' => now()->subDay()->toDateString()]);
        $this->getJson('/api/sertifikat/verifikasi?nomor=00119316')->assertJsonPath('data.status', 'tidak_berlaku');
    });
});
