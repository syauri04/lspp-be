<?php

use App\Models\Asesi;

it('asesi tanpa foto mendapat avatar bawaan, yang punya foto tetap fotonya', function () {
    $tanpa = Asesi::factory()->create(['avatar' => null]);
    $dengan = Asesi::factory()->create(['avatar' => 'https://lh3.googleusercontent.com/a/foto']);

    $this->withToken($tanpa->createToken('auth_token')->plainTextToken)->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.avatar', null)
        ->assertJsonPath('data.avatar_url', asset('storage/avatars/default.svg'));

    expect($dengan->avatar_url)->toBe('https://lh3.googleusercontent.com/a/foto')
        ->and(file_exists(storage_path('app/public/avatars/default.svg')))->toBeTrue();
});
