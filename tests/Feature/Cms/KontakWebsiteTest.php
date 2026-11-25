<?php

use App\Models\User;
use App\Support\KontakLembaga;

it('API publik mengembalikan kontak bawaan tanpa token, dengan cache header & tanpa data internal', function () {
    $this->getJson('/api/kontak')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=300, public')
        ->assertExactJson(['success' => true, 'data' => [
            'nama' => 'Admin LSPP306',
            'email' => 'tigapuluhjuni@gmail.com',
            'alamat' => KontakLembaga::BAWAAN['alamat'],
            'whatsapp' => [
                'nomor' => '6282249628093',
                'tampil' => '+62 822-4962-8093',
                'url' => 'https://wa.me/6282249628093?text='.rawurlencode(KontakLembaga::BAWAAN['pesan_wa']),
            ],
        ]]);
});

it('admin CMS mengubah kontak: nomor dinormalisasi, email huruf kecil, langsung tampil di API', function () {
    $admin = User::factory()->create(['name' => 'Admin Konten']);

    $this->actingAs($admin)->get(route('kontak-website.edit'))->assertOk()->assertSee('+62 822-4962-8093');

    $this->actingAs($admin)->put(route('kontak-website.update'), [
        'nama' => '  CS LSPP 306 ',
        'whatsapp' => '0812 3456-7890',
        'email' => 'Info@LSPP306.com',
        'alamat' => "Jl. Contoh No. 1\n  Jakarta",
        'pesan_wa' => '',
    ])->assertRedirect(route('kontak-website.edit'))->assertSessionHasNoErrors();

    $this->getJson('/api/kontak')
        ->assertJsonPath('data.nama', 'CS LSPP 306')
        ->assertJsonPath('data.email', 'info@lspp306.com')
        ->assertJsonPath('data.alamat', 'Jl. Contoh No. 1 Jakarta')
        ->assertJsonPath('data.whatsapp.nomor', '6281234567890')
        ->assertJsonPath('data.whatsapp.tampil', '+62 812-3456-7890')
        ->assertJsonPath('data.whatsapp.url', 'https://wa.me/6281234567890')
        ->assertJsonMissingPath('data.diubah_oleh');

    $this->actingAs($admin)->get(route('kontak-website.edit'))->assertSee('Terakhir diubah oleh Admin Konten');
});

it('menolak input tidak valid dan tamu tidak bisa mengakses CMS', function () {
    $admin = User::factory()->create();
    $sah = ['nama' => 'Admin', 'whatsapp' => '081234567890', 'email' => 'a@b.co', 'alamat' => 'Jakarta', 'pesan_wa' => null];

    foreach ([
        'whatsapp' => ['12345', '+1 202 555 0100', '0212345678'],   // terlalu pendek, luar negeri, bukan seluler
        'email' => ['bukan-email'],
        'nama' => [''],
        'alamat' => [str_repeat('a', 501)],
        'pesan_wa' => [str_repeat('a', 301)],
    ] as $field => $salah) {
        foreach ($salah as $nilai) {
            $this->actingAs($admin)->put(route('kontak-website.update'), [...$sah, $field => $nilai])->assertSessionHasErrors($field);
        }
    }
    expect(KontakLembaga::ambil()['nama'])->toBe('Admin LSPP306');

    auth()->logout();
    $this->get(route('kontak-website.edit'))->assertRedirect();
    $this->put(route('kontak-website.update'), $sah)->assertRedirect();
    expect(KontakLembaga::ambil()['nama'])->toBe('Admin LSPP306');
});
