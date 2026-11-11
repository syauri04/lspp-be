<?php

use App\Models\Asesi;
use App\Models\User;
use App\Notifications\AsesiResetPasswordNotifikasi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Notification::fake();
    Cache::flush();
});

it('kirim link: respons sama untuk email terdaftar & tidak terdaftar; link memuat bahasa', function () {
    $asesi = Asesi::factory()->create();

    $terdaftar = $this->postJson('/api/auth/forgot-password', ['email' => $asesi->email], ['X-Locale' => 'en'])->assertOk();
    $tidak = $this->postJson('/api/auth/forgot-password', ['email' => 'tidakada@example.com'], ['X-Locale' => 'en'])->assertOk();

    expect($terdaftar->json('message'))->toBe($tidak->json('message'));

    Notification::assertSentTo($asesi, AsesiResetPasswordNotifikasi::class, function ($n) use ($asesi) {
        $url = $n->toMail($asesi)->actionUrl;

        return $n->lang === 'en' && str_contains($url, '/en/reset-password?token=') && str_contains($url, urlencode($asesi->email));
    });
    expect(DB::table('asesi_password_reset_tokens')->where('email', $asesi->email)->exists())->toBeTrue()
        ->and(DB::table('password_reset_tokens')->count())->toBe(0); // tidak memakai tabel admin
});

it('akun nonaktif tidak dikirimi link (respons tetap sukses)', function () {
    $asesi = Asesi::factory()->create();
    $asesi->forceFill(['nonaktif_at' => now()])->save();

    $this->postJson('/api/auth/forgot-password', ['email' => $asesi->email])->assertOk();
    Notification::assertNothingSent();
});

it('reset: password berganti, token sekali pakai, semua sesi dicabut, email terverifikasi', function () {
    $asesi = Asesi::factory()->unverified()->create();
    $asesi->createToken('auth_token');
    $token = Password::broker('asesis')->createToken($asesi);

    $data = ['token' => $token, 'email' => $asesi->email, 'password' => 'BaruSekali#9', 'password_confirmation' => 'BaruSekali#9'];
    $this->postJson('/api/auth/reset-password', $data)->assertOk();

    $asesi->refresh();
    expect(Hash::check('BaruSekali#9', $asesi->password))->toBeTrue()
        ->and($asesi->hasVerifiedEmail())->toBeTrue()
        ->and($asesi->tokens()->count())->toBe(0);

    $this->postJson('/api/auth/reset-password', $data)->assertStatus(422)->assertJsonPath('kode', 'token_tidak_valid');
});

it('reset: aturan password dua bahasa & token salah ditolak', function () {
    $asesi = Asesi::factory()->create();
    $token = Password::broker('asesis')->createToken($asesi);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token, 'email' => $asesi->email, 'password' => 'qwer1234', 'password_confirmation' => 'qwer1234',
    ], ['X-Locale' => 'en'])->assertStatus(422)
        ->assertJsonPath('errors.password.0', 'Password must contain both uppercase and lowercase letters.');

    $this->postJson('/api/auth/reset-password', [
        'token' => 'salah', 'email' => $asesi->email, 'password' => 'BaruSekali#9', 'password_confirmation' => 'BaruSekali#9',
    ])->assertStatus(422)->assertJsonPath('message', 'Link atur ulang password tidak valid atau sudah kedaluwarsa. Silakan minta link baru.');
});

it('token reset asesi tidak berlaku untuk admin CMS dengan email yang sama', function () {
    $admin = User::factory()->create(['email' => 'sama@example.com', 'password' => 'AdminLama#1']);
    $asesi = Asesi::factory()->create(['email' => 'sama@example.com']);
    $tokenAsesi = Password::broker('asesis')->createToken($asesi);

    $status = Password::broker('users')->reset(
        ['email' => 'sama@example.com', 'token' => $tokenAsesi, 'password' => 'Dibobol#123', 'password_confirmation' => 'Dibobol#123'],
        fn ($u, $p) => $u->forceFill(['password' => Hash::make($p)])->save(),
    );

    expect($status)->toBe(Password::INVALID_TOKEN)
        ->and(Hash::check('AdminLama#1', $admin->fresh()->password))->toBeTrue();
});

it('captcha wajib bila aktif', function () {
    config(['captcha.aktif' => true]);
    $this->postJson('/api/auth/forgot-password', ['email' => 'x@example.com'])
        ->assertStatus(422)->assertJsonValidationErrors(['captcha', 'captcha_id']);
});
