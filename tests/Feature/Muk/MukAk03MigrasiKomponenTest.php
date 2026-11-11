<?php

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use Tests\Support\MukFixture;

/** Jalankan ulang migrasi sisip komponen AK.03 terhadap data yang sudah ada. */
function ak03Migrasi(): object
{
    return require database_path('migrations/2026_11_12_100000_sisip_komponen_kerahasiaan_ak03.php');
}

it('menyisipkan komponen kerahasiaan di urutan 9 tanpa menggeser jawaban lain; idempoten & dapat dibatalkan', function () {
    $p = MukFixture::penilaian();
    $jenis = JenisDokumenMuk::where('kode', JenisDokumenMuk::AK_03)->firstOrFail();
    $lama = collect(range(1, 9))->map(fn ($i) => ['hasil' => 'ya', 'catatan' => "butir {$i}"])->all();
    $lama[8] = ['hasil' => 'tidak', 'catatan' => 'komunikasi kurang'];
    $d = DokumenPenilaian::create([
        'penilaian_muk_id' => $p->id,
        'jenis_dokumen_muk_id' => $jenis->id,
        'status' => DokumenPenilaian::STATUS_SELESAI,
        'data' => ['komponen' => $lama, 'catatan_lainnya' => 'Terima kasih'],
    ]);

    ak03Migrasi()->up();
    $data = $d->fresh()->data;
    expect($data['komponen'])->toHaveCount(10)
        ->and($data['komponen'][7]['catatan'])->toBe('butir 8')
        ->and($data['komponen'][8])->toBe(['hasil' => null, 'catatan' => null])
        ->and($data['komponen'][9])->toBe(['hasil' => 'tidak', 'catatan' => 'komunikasi kurang'])
        ->and($data['catatan_lainnya'])->toBe('Terima kasih');

    ak03Migrasi()->up(); // idempoten
    expect($d->fresh()->data['komponen'])->toHaveCount(10);

    ak03Migrasi()->down();
    expect($d->fresh()->data['komponen'])->toBe($lama);
});
