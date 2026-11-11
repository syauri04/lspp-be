<?php

use App\Models\Asesi;
use App\Models\Asesor;
use Illuminate\Support\Facades\Hash;
use Tests\Support\MukFixture;

const PW_LAMA = 'Awal#2026';
const PW_BARU = 'Baru#2026x';

it('asesor mengganti password: wajib password lama, aturan kuat, sesi lain dicabut, sesi ini tetap', function () {
    $asesor = Asesor::factory()->create(['password' => PW_LAMA]);
    $token = MukFixture::token($asesor);
    $lain = MukFixture::token($asesor); // perangkat lain

    $this->withToken($token)->putJson('/api/muk/auth/password', [
        'current_password' => 'salah', 'password' => PW_BARU, 'password_confirmation' => PW_BARU,
    ])->assertJsonValidationErrors(['current_password' => 'Password saat ini salah.']);

    $this->withToken($token)->putJson('/api/muk/auth/password', [
        'current_password' => PW_LAMA, 'password' => 'lemah', 'password_confirmation' => 'lemah',
    ])->assertJsonValidationErrors('password');

    $this->withToken($token)->putJson('/api/muk/auth/password', [
        'current_password' => PW_LAMA, 'password' => PW_LAMA, 'password_confirmation' => PW_LAMA,
    ])->assertJsonValidationErrors(['password' => 'Password baru harus berbeda dengan password saat ini.']);

    $this->withToken($token)->putJson('/api/muk/auth/password', [
        'current_password' => PW_LAMA, 'password' => PW_BARU, 'password_confirmation' => 'Beda#2026x',
    ])->assertJsonValidationErrors('password');
    expect(Hash::check(PW_LAMA, $asesor->fresh()->password))->toBeTrue();

    $this->withToken($token)->putJson('/api/muk/auth/password', [
        'current_password' => PW_LAMA, 'password' => PW_BARU, 'password_confirmation' => PW_BARU,
    ])->assertOk();

    expect(Hash::check(PW_BARU, $asesor->fresh()->password))->toBeTrue()
        ->and($asesor->tokens()->count())->toBe(1);
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/muk/auth/me')->assertOk();
    app('auth')->forgetGuards();
    $this->withToken($lain)->getJson('/api/muk/auth/me')->assertUnauthorized();

    // Login dengan password baru berhasil, password lama ditolak.
    app('auth')->forgetGuards();
    $this->postJson('/api/muk/auth/asesor/login', ['email' => $asesor->email, 'password' => PW_BARU])->assertOk();
    $this->postJson('/api/muk/auth/asesor/login', ['email' => $asesor->email, 'password' => PW_LAMA])->assertUnprocessable();
});

it('hanya asesor (asesi & tamu ditolak) dan dibatasi 5 percobaan per menit', function () {
    $asesi = Asesi::factory()->create();
    $body = ['current_password' => 'x', 'password' => PW_BARU, 'password_confirmation' => PW_BARU];

    $this->putJson('/api/muk/auth/password', $body)->assertUnauthorized();
    $this->withToken(MukFixture::token($asesi))->putJson('/api/muk/auth/password', $body)->assertForbidden();
    app('auth')->forgetGuards();

    $token = MukFixture::token(Asesor::factory()->create(['password' => PW_LAMA]));
    foreach (range(1, 5) as $_) {
        $this->withToken($token)->putJson('/api/muk/auth/password', $body)->assertUnprocessable();
    }
    $this->withToken($token)->putJson('/api/muk/auth/password', $body)->assertTooManyRequests();
});
