<?php

use App\Models\Asesi;

$dataLemah = ['name' => 'Rifan', 'email' => 'rifan@example.com', 'password' => 'qwer1234', 'password_confirmation' => 'qwer1234'];

it('pesan validasi password register dalam Bahasa Indonesia (bawaan)', function () use ($dataLemah) {
    $this->postJson('/api/auth/register', $dataLemah)
        ->assertStatus(422)
        ->assertJsonPath('errors.password', [
            'Password harus berisi huruf besar dan huruf kecil.',
            'Password harus berisi minimal satu simbol (mis. ! @ # ?).',
        ]);
});

it('pesan validasi register dalam Bahasa Inggris dengan header X-Locale: en', function () use ($dataLemah) {
    Asesi::factory()->create(['email' => 'rifan@example.com']);

    $this->postJson('/api/auth/register', $dataLemah, ['X-Locale' => 'en'])
        ->assertStatus(422)
        ->assertJsonPath('errors.password', [
            'Password must contain both uppercase and lowercase letters.',
            'Password must contain at least one symbol (e.g. ! @ # ?).',
        ])
        ->assertJsonPath('errors.email.0', 'This email is already registered. Please log in.');
});
