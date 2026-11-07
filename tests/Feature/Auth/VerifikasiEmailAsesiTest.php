<?php

use App\Models\Asesi;
use Illuminate\Support\Facades\URL;

/** Query string link verifikasi (sama seperti yang ditempel lspp-fe dari email). */
function queryVerifikasi(Asesi $asesi, ?string $hash = null): string
{
    $url = URL::temporarySignedRoute('api.asesi.verify-email', now()->addHour(), [
        'id' => $asesi->id,
        'hash' => $hash ?? sha1($asesi->getEmailForVerification()),
    ]);

    return '?'.parse_url($url, PHP_URL_QUERY);
}

it('memverifikasi email tanpa login otomatis (tidak ada token)', function () {
    $asesi = Asesi::factory()->unverified()->create();

    $res = $this->postJson('/api/auth/email/verify'.queryVerifikasi($asesi))
        ->assertOk()
        ->assertJsonPath('data.sudah_terverifikasi', false)
        ->assertJsonPath('message', 'Email berhasil diverifikasi. Silakan login untuk melanjutkan.');

    expect($res->json('data.token'))->toBeNull()
        ->and($asesi->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($asesi->tokens()->count())->toBe(0);
});

it('idempoten: membuka link yang sama lagi tetap sukses', function () {
    $asesi = Asesi::factory()->unverified()->create();
    $q = queryVerifikasi($asesi);

    $this->postJson('/api/auth/email/verify'.$q)->assertOk();
    $this->postJson('/api/auth/email/verify'.$q)
        ->assertOk()
        ->assertJsonPath('data.sudah_terverifikasi', true);
});

it('menolak tanda tangan rusak, hash salah, dan link kedaluwarsa', function () {
    $asesi = Asesi::factory()->unverified()->create();

    $this->postJson('/api/auth/email/verify'.queryVerifikasi($asesi).'x')->assertForbidden();
    $this->postJson('/api/auth/email/verify'.queryVerifikasi($asesi, sha1('lain@example.com')))
        ->assertForbidden()->assertJsonPath('message', 'Link verifikasi tidak valid.');

    $kedaluwarsa = URL::temporarySignedRoute('api.asesi.verify-email', now()->subMinute(), [
        'id' => $asesi->id, 'hash' => sha1($asesi->getEmailForVerification()),
    ]);
    $this->postJson('/api/auth/email/verify?'.parse_url($kedaluwarsa, PHP_URL_QUERY))->assertForbidden();

    expect($asesi->fresh()->hasVerifiedEmail())->toBeFalse();
});
