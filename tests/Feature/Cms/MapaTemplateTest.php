<?php

use App\Models\MapaTemplate;
use App\Models\MapaTemplatePenandatangan;
use App\Models\MapaTemplateRencana;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MukFixture;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->create();
});

function gambarTtd(string $nama = 'ttd.png'): UploadedFile
{
    return UploadedFile::fake()->image($nama, 300, 100);
}

it('halaman template dibuat per skema+metode dengan 4 baris konfirmasi tetap', function () {
    $skema = MukFixture::skema();

    $this->actingAs($this->admin)->get(route('skema-sertifikasi.mapa.edit', [$skema, 'vp']))
        ->assertOk()
        ->assertSee('Unit 1')
        ->assertSee('Manajer sertifikasi LSP');

    $template = MapaTemplate::where('skema_sertifikasi_id', $skema->id)->where('metode', 'vp')->first();
    expect($template->penandatangan()->where('bagian', 'konfirmasi')->count())->toBe(4);

    $this->actingAs($this->admin)->get(route('skema-sertifikasi.mapa.edit', [$skema, 'xx']))->assertNotFound();
    auth()->logout();
    $this->get(route('skema-sertifikasi.mapa.edit', [$skema, 'kt']))->assertRedirect();
});

it('menyimpan rencana §2 per unit, penandatangan, dan tanda tangan (di-encode ulang, disk privat)', function () {
    $skema = MukFixture::skema();
    $unit = $skema->unitKompetensis()->first();
    $unitLain = MukFixture::skema()->unitKompetensis()->first();
    $this->actingAs($this->admin)->get(route('skema-sertifikasi.mapa.edit', [$skema, 'vp']));
    $konfirmasi = MapaTemplatePenandatangan::where('bagian', 'konfirmasi')->orderBy('urutan')->get();

    $this->actingAs($this->admin)->put(route('skema-sertifikasi.mapa.update', [$skema, 'vp']), [
        'rencana' => [
            $unit->id => ['bukti' => 'Hasil Verifikasi Portofolio', 'jenis_l' => 0, 'jenis_tl' => 1, 'jenis_t' => 1, 'tanya_jawab' => 'PW, DPT', 'verifikasi_portofolio' => 'CVP'],
            $unitLain->id => ['bukti' => 'Bukan milik skema ini'],
        ],
        'penandatangan' => [
            'konfirmasi' => [
                ['id' => $konfirmasi[0]->id, 'peran' => $konfirmasi[0]->peran, 'nama' => 'Dr. Ricardo Indra, M.Si.', 'tanggal' => '2026-06-08', 'ttd' => gambarTtd()],
            ],
            'penyusun_mapa01' => [
                ['peran' => 'penyusun', 'nama' => 'Munif Sofian, S. Kom.', 'no_reg' => 'MET. 000.006177 2025', 'tanggal' => '2026-06-08', 'ttd' => gambarTtd()],
                ['peran' => 'validator', 'nama' => 'Alma Fanniya Idral, M.Si.', 'no_reg' => 'MET. 000.009936 2023'],
            ],
        ],
        'ada_penyusun_mapa01' => 1,
    ])->assertRedirect(route('skema-sertifikasi.mapa.edit', [$skema, 'vp']))->assertSessionHasNoErrors();

    $r = MapaTemplateRencana::where('unit_kompetensi_id', $unit->id)->first();
    expect($r->jenis_tl)->toBeTrue()->and($r->jenis_l)->toBeFalse()->and($r->verifikasi_portofolio)->toBe('CVP')
        ->and(MapaTemplateRencana::where('unit_kompetensi_id', $unitLain->id)->exists())->toBeFalse();

    $penyusun = MapaTemplatePenandatangan::where('bagian', 'penyusun_mapa01')->orderBy('urutan')->get();
    expect($penyusun)->toHaveCount(2)
        ->and($penyusun[0]->path_ttd)->toStartWith('mapa-template/')
        ->and($konfirmasi[0]->fresh()->nama)->toBe('Dr. Ricardo Indra, M.Si.');
    Storage::disk('local')->assertExists($penyusun[0]->path_ttd);
    expect(getimagesizefromstring(Storage::disk('local')->get($penyusun[0]->path_ttd))[2])->toBe(IMAGETYPE_PNG);

    $this->actingAs($this->admin)->get(route('mapa-template.ttd', $penyusun[0]))->assertOk()->assertHeader('Content-Type', 'image/png');

    // Hapus semua penyusun MAPA.01 -> baris & berkas ikut terhapus.
    $path = $penyusun[0]->path_ttd;
    $this->actingAs($this->admin)->put(route('skema-sertifikasi.mapa.update', [$skema, 'vp']), ['ada_penyusun_mapa01' => 1])
        ->assertSessionHasNoErrors();
    expect(MapaTemplatePenandatangan::where('bagian', 'penyusun_mapa01')->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

it('menolak berkas tanda tangan bukan gambar & penyusun tanpa nama', function () {
    $skema = MukFixture::skema();

    $this->actingAs($this->admin)->put(route('skema-sertifikasi.mapa.update', [$skema, 'kt']), [
        'penandatangan' => ['penyusun_mapa02' => [
            ['peran' => 'penyusun', 'nama' => 'A', 'ttd' => UploadedFile::fake()->create('ttd.pdf', 10, 'application/pdf')],
            ['peran' => 'validator', 'nama' => ''],
        ]],
    ])->assertSessionHasErrors(['penandatangan.penyusun_mapa02.0.ttd', 'penandatangan.penyusun_mapa02.1.nama']);

    expect(MapaTemplatePenandatangan::where('bagian', 'penyusun_mapa02')->count())->toBe(0);
});
