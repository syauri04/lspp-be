<?php

use App\Models\Apl02Jawaban;
use App\Models\ElemenKompetensi;
use App\Models\JenisDokumenMuk;
use App\Models\User;
use Tests\Support\MukFixture;

/**
 * Simpan elemen di CMS memakai sinkronisasi ID: jawaban FR.APL.02 asesi tidak boleh
 * terputus/hilang karena elemen dihapus-lalu-dibuat-ulang.
 */
function formElemen($unit, array $ubah = []): array
{
    return $unit->elemenKompetensi()->with('kriteriaUnjukKerja')->get()->map(fn ($e) => [
        'id' => $e->id,
        'nama_elemen' => $ubah[$e->id] ?? $e->nama_elemen,
        'kuk' => $e->kriteriaUnjukKerja->map(fn ($k) => ['nomor_kuk' => $k->nomor_kuk, 'deskripsi' => $k->deskripsi])->all(),
    ])->all();
}

function urlElemen($skema, $unit): string
{
    return route('skema-sertifikasi.unit-kompetensi.elemen.update', [$skema, $unit]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('mengubah elemen mempertahankan ID dan jawaban APL.02', function () {
    $p = MukFixture::penilaian();
    $skema = $p->pendaftaran->skemaSertifikasi;
    $unit = $skema->unitKompetensis()->first();
    $elemen = $unit->elemenKompetensi()->first();
    $dok = MukFixture::dokumen($p, JenisDokumenMuk::APL_02);
    Apl02Jawaban::create(['dokumen_penilaian_id' => $dok->id, 'elemen_kompetensi_id' => $elemen->id, 'kompeten' => 'K']);

    $form = formElemen($unit, [$elemen->id => 'Nama Baru']);
    $form[] = ['id' => null, 'nama_elemen' => 'Elemen Baru', 'kuk' => [['nomor_kuk' => '9.1', 'deskripsi' => 'Baru']]];

    $this->put(urlElemen($skema, $unit), ['elemen' => $form])->assertSessionHasNoErrors();

    expect(ElemenKompetensi::find($elemen->id)->nama_elemen)->toBe('Nama Baru')
        ->and($unit->elemenKompetensi()->count())->toBe(3)
        ->and(Apl02Jawaban::where('elemen_kompetensi_id', $elemen->id)->exists())->toBeTrue();
});

it('menolak menghapus elemen yang sudah dijawab asesi', function () {
    $p = MukFixture::penilaian();
    $skema = $p->pendaftaran->skemaSertifikasi;
    $unit = $skema->unitKompetensis()->first();
    [$dijawab, $lain] = $unit->elemenKompetensi()->get()->all();
    Apl02Jawaban::create([
        'dokumen_penilaian_id' => MukFixture::dokumen($p, JenisDokumenMuk::APL_02)->id,
        'elemen_kompetensi_id' => $dijawab->id, 'kompeten' => 'BK',
    ]);

    $form = collect(formElemen($unit))->reject(fn ($e) => $e['id'] === $dijawab->id)->values()->all();

    $this->put(urlElemen($skema, $unit), ['elemen' => $form])->assertSessionHasErrors('elemen');
    expect(ElemenKompetensi::find($dijawab->id))->not->toBeNull();

    // Elemen yang belum dijawab boleh dihapus.
    $form = collect(formElemen($unit))->reject(fn ($e) => $e['id'] === $lain->id)->values()->all();
    $this->put(urlElemen($skema, $unit), ['elemen' => $form])->assertSessionHasNoErrors();
    expect(ElemenKompetensi::find($lain->id))->toBeNull();
});

it('menolak ID elemen milik unit lain', function () {
    $p = MukFixture::penilaian();
    $skema = $p->pendaftaran->skemaSertifikasi;
    [$unit, $unitLain] = $skema->unitKompetensis()->get()->all();
    $form = formElemen($unit);
    $form[0]['id'] = $unitLain->elemenKompetensi()->value('id');

    $this->put(urlElemen($skema, $unit), ['elemen' => $form])->assertSessionHasErrors('elemen');
});
