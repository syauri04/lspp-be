<?php

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

function loginMuk(string $peran, string $email, string $password = 'password')
{
    return test()->postJson("/api/muk/auth/{$peran}/login", compact('email', 'password'));
}

// Guard sanctum meng-cache user dalam satu instance app; reset antar request di test yang sama.
function lupakanGuard(): void
{
    app('auth')->forgetGuards();
}

describe('login asesi', function () {
    it('berhasil dan mengembalikan token berability muk:asesi dengan expiry 8 jam', function () {
        $asesi = Asesi::factory()->create();

        $res = loginMuk('asesi', $asesi->email)
            ->assertOk()
            ->assertJsonPath('data.user.role', 'asesi')
            ->assertJsonPath('data.user.email', $asesi->email)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonMissingPath('data.user.password');

        $token = PersonalAccessToken::findToken($res->json('data.token'));
        expect($token->abilities)->toBe(['muk:asesi'])
            ->and($token->name)->toBe('muk')
            ->and(abs($token->expires_at->diffInMinutes(now()->addMinutes(480))))->toBeLessThan(2);
    });

    it('menolak password salah dan email tak terdaftar dengan pesan yang sama', function () {
        $asesi = Asesi::factory()->create();

        $salah = loginMuk('asesi', $asesi->email, 'keliru')->assertStatus(422)->json('errors.email.0');
        $tidakAda = loginMuk('asesi', 'tidak-ada@contoh.test')->assertStatus(422)->json('errors.email.0');

        expect($salah)->toBe($tidakAda)->toContain('Email atau kata sandi salah');
    });

    it('menolak asesi Google yang belum membuat password', function () {
        $asesi = Asesi::factory()->tanpaPassword()->create();

        loginMuk('asesi', $asesi->email)->assertStatus(422)
            ->assertJsonPath('errors.email.0', fn ($m) => str_contains($m, 'buat kata sandi'));
    });

    it('menolak asesi yang emailnya belum diverifikasi', function () {
        $asesi = Asesi::factory()->unverified()->create();

        loginMuk('asesi', $asesi->email)->assertStatus(422);
        expect($asesi->tokens()->count())->toBe(0);
    });
});

describe('login asesor', function () {
    it('berhasil dan mengembalikan token berability muk:asesor', function () {
        $asesor = Asesor::factory()->create();

        $res = loginMuk('asesor', $asesor->email)
            ->assertOk()
            ->assertJsonPath('data.user.role', 'asesor')
            ->assertJsonPath('data.user.name', $asesor->nama)
            ->assertJsonPath('data.user.nomor_registrasi', $asesor->nomor_registrasi);

        expect(PersonalAccessToken::findToken($res->json('data.token'))->abilities)->toBe(['muk:asesor']);
    });

    it('menolak asesor nonaktif', function () {
        $asesor = Asesor::factory()->nonaktif()->create();

        loginMuk('asesor', $asesor->email)->assertStatus(422);
    });

    it('menolak asesor yang sudah dihapus (soft delete) dengan pesan generik', function () {
        $asesor = Asesor::factory()->create();
        $asesor->delete();

        loginMuk('asesor', $asesor->email)->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Email atau kata sandi salah.');
    });

    it('tidak bisa login lewat endpoint asesi MUK maupun login lspp-fe', function () {
        $asesor = Asesor::factory()->create();

        loginMuk('asesi', $asesor->email)->assertStatus(422);
        $this->postJson('/api/auth/login', ['email' => $asesor->email, 'password' => 'password'])->assertStatus(422);
    });
});

describe('throttle', function () {
    it('memblokir setelah 5 percobaan gagal per email, walau password ke-6 benar', function () {
        $asesi = Asesi::factory()->create();

        foreach (range(1, 5) as $_) {
            loginMuk('asesi', $asesi->email, 'keliru')->assertStatus(422);
        }

        loginMuk('asesi', $asesi->email)->assertStatus(429);
    });

    it('membatasi 20 request login per menit per IP lintas email', function () {
        foreach (range(1, 20) as $i) {
            loginMuk('asesi', "orang{$i}@contoh.test", 'keliru')->assertStatus(422);
        }

        loginMuk('asesi', 'orang21@contoh.test', 'keliru')->assertStatus(429);
    });
});

describe('me & logout', function () {
    it('me mengembalikan profil + role sesuai token', function () {
        $asesor = Asesor::factory()->create();
        $token = loginMuk('asesor', $asesor->email)->json('data.token');

        $this->withToken($token)->getJson('/api/muk/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'asesor')
            ->assertJsonPath('data.id', $asesor->id);
    });

    it('logout merevoke token saat ini', function () {
        $asesi = Asesi::factory()->create();
        $token = loginMuk('asesi', $asesi->email)->json('data.token');

        $this->withToken($token)->postJson('/api/muk/auth/logout')->assertOk();
        lupakanGuard();

        $this->withToken($token)->getJson('/api/muk/auth/me')->assertUnauthorized();
    });

    it('token kedaluwarsa ditolak', function () {
        $asesi = Asesi::factory()->create();
        $token = loginMuk('asesi', $asesi->email)->json('data.token');

        $this->travel(481)->minutes();

        $this->withToken($token)->getJson('/api/muk/auth/me')->assertUnauthorized();
    });

    it('asesor yang dinonaktifkan setelah login langsung kehilangan akses', function () {
        $asesor = Asesor::factory()->create();
        $token = loginMuk('asesor', $asesor->email)->json('data.token');

        $asesor->update(['status' => 'nonaktif']);

        $this->withToken($token)->getJson('/api/muk/auth/me')->assertUnauthorized();
        expect($asesor->tokens()->count())->toBe(0);
    });
});

describe('pemisahan token MUK dan lspp-fe', function () {
    it('token lspp-fe (ability *) ditolak di endpoint MUK', function () {
        $asesi = Asesi::factory()->create();
        $tokenWebsite = $asesi->createToken('auth_token')->plainTextToken;

        $this->withToken($tokenWebsite)->getJson('/api/muk/auth/me')->assertForbidden();
    });

    it('token MUK asesi ditolak di endpoint akun lspp-fe', function () {
        $asesi = Asesi::factory()->create();
        $token = loginMuk('asesi', $asesi->email)->json('data.token');

        $this->withToken($token)->getJson('/api/auth/me')->assertForbidden();
    });

    it('token MUK asesor ditolak di endpoint akun & pendaftaran lspp-fe', function () {
        $asesor = Asesor::factory()->create();
        $token = loginMuk('asesor', $asesor->email)->json('data.token');

        $this->withToken($token)->getJson('/api/auth/me')->assertForbidden();
        $this->withToken($token)->postJson('/api/auth/change-password', [])->assertForbidden();
        $this->withToken($token)->getJson('/api/pendaftaran-sertifikasi')->assertForbidden();
    });

    it('token lspp-fe tetap berfungsi di endpoint akun lspp-fe (regresi)', function () {
        $asesi = Asesi::factory()->create();
        $tokenWebsite = $asesi->createToken('auth_token')->plainTextToken;

        $this->withToken($tokenWebsite)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $asesi->email);
    });

    it('token dari model lain (mis. User admin) ditolak di MUK', function () {
        $user = User::factory()->create();
        $token = $user->createToken('x', ['muk:asesi'])->plainTextToken;

        $this->withToken($token)->getJson('/api/muk/auth/me')->assertForbidden();
    });
});

describe('CORS', function () {
    it('mengizinkan origin MUK dan menolak origin asing', function () {
        $preflight = fn (string $origin) => $this->call('OPTIONS', '/api/muk/auth/asesi/login', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        expect($preflight('http://localhost:3001')->headers->get('Access-Control-Allow-Origin'))->toBe('http://localhost:3001')
            ->and($preflight('https://jahat.example')->headers->get('Access-Control-Allow-Origin'))->toBeNull();
    });
});
