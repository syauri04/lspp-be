<?php

use App\Models\DokumenPenilaian;
use App\Models\PenilaianMuk;
use App\Models\User;
use Tests\Support\MukFixture;

it('daftar asesmen menampilkan metode & hasil MUK, dapat difilter; detail menampilkan progres dokumen', function () {
    $admin = User::factory()->create();
    $selesai = MukFixture::penilaian();
    $selesai->update(['metode_asesmen' => 'tpd', 'status' => PenilaianMuk::STATUS_SELESAI_KOMPETEN, 'selesai_at' => now()]);
    $selesai->jadwalAsesmen->update(['status' => 'selesai']);
    $berjalan = MukFixture::penilaian();

    $this->actingAs($admin)->get(route('asesmen.index'))
        ->assertOk()
        ->assertSee('Kompeten')
        ->assertSee('TPD');

    $this->actingAs($admin)->get(route('asesmen.index', ['hasil' => 'kompeten']))
        ->assertOk()
        ->assertSee($selesai->pendaftaran->kode_pendaftaran)
        ->assertDontSee($berjalan->pendaftaran->kode_pendaftaran);

    $this->actingAs($admin)->get(route('asesmen.show', $selesai->jadwalAsesmen))
        ->assertOk()
        ->assertSee('Progres Asesmen (MUK)')
        ->assertSee('Selesai - Kompeten')
        ->assertSee('FR.APL.01');
    expect($selesai->dokumenPenilaian()->where('status', DokumenPenilaian::STATUS_BELUM_DIISI)->exists())->toBeTrue();
});
