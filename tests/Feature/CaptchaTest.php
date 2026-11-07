<?php

use App\Models\Asesi;
use App\Services\Captcha\CaptchaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['captcha.aktif' => true]);
    Cache::flush();
});

/** Ambil captcha baru + kode aslinya (dibaca dari random_int yang dikunci tidak mungkin; pakai hash cache). */
function captchaDenganKode(string $kode = 'ABC234'): array
{
    // Buat lewat API, lalu timpa hash di cache dengan kode yang diketahui test.
    $id = test()->getJson('/api/auth/captcha')->json('data.id');
    Cache::put("captcha:{$id}", hash_hmac('sha256', $kode, (string) config('app.key')), 300);

    return [$id, $kode];
}

it('GET captcha: id & gambar PNG baru tiap permintaan, tidak di-cache', function () {
    $a = $this->getJson('/api/auth/captcha')->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    $b = $this->getJson('/api/auth/captcha')->assertOk();

    expect($a->json('data.id'))->not->toBe($b->json('data.id'))
        ->and($a->json('data.gambar'))->toStartWith('data:image/png;base64,')
        ->and($a->json('data.gambar'))->not->toBe($b->json('data.gambar'))
        ->and(imagecreatefromstring(base64_decode(substr($a->json('data.gambar'), 22))))->not->toBeFalse()
        ->and($a->json('data.kedaluwarsa_detik'))->toBe(300);
});

it('verifikasi: tidak peka huruf/spasi, sekali pakai, salah langsung hangus', function () {
    $svc = app(CaptchaService::class);

    [$id, $kode] = captchaDenganKode();
    expect($svc->verifikasi($id, ' abc 234 '))->toBeTrue()
        ->and($svc->verifikasi($id, $kode))->toBeFalse(); // sudah dipakai

    [$id2] = captchaDenganKode();
    expect($svc->verifikasi($id2, 'SALAH1'))->toBeFalse()
        ->and($svc->verifikasi($id2, 'ABC234'))->toBeFalse(); // hangus setelah salah

    expect($svc->verifikasi('bukan-uuid', 'ABC234'))->toBeFalse()
        ->and($svc->verifikasi(null, null))->toBeFalse();
});

it('login ditolak tanpa/dengan captcha salah, berhasil dengan captcha benar', function () {
    $asesi = Asesi::factory()->create(['password' => 'Rahasia#123', 'email_verified_at' => now()]);
    $kredensial = ['email' => $asesi->email, 'password' => 'Rahasia#123'];

    $this->postJson('/api/auth/login', $kredensial)
        ->assertStatus(422)->assertJsonValidationErrors(['captcha', 'captcha_id']);

    [$id] = captchaDenganKode();
    $this->postJson('/api/auth/login', [...$kredensial, 'captcha_id' => $id, 'captcha' => 'XXXXXX'])
        ->assertStatus(422)
        ->assertJsonPath('errors.captcha.0', 'Kode captcha salah atau sudah kedaluwarsa. Silakan coba kode yang baru.');

    [$id, $kode] = captchaDenganKode();
    $this->postJson('/api/auth/login', [...$kredensial, 'captcha_id' => $id, 'captcha' => strtolower($kode)])
        ->assertOk()->assertJsonStructure(['data' => ['token']]);

    // Captcha yang sama tidak bisa dipakai ulang.
    $this->postJson('/api/auth/login', [...$kredensial, 'captcha_id' => $id, 'captcha' => $kode])
        ->assertStatus(422)->assertJsonValidationErrors('captcha');
});

it('register membutuhkan captcha yang benar', function () {
    Notification::fake();
    $data = [
        'name' => 'Asesi Baru',
        'email' => 'baru@example.com',
        'password' => 'Rahasia#123',
        'password_confirmation' => 'Rahasia#123',
    ];

    $this->postJson('/api/auth/register', $data)->assertStatus(422)->assertJsonValidationErrors('captcha');
    expect(Asesi::where('email', 'baru@example.com')->exists())->toBeFalse();

    [$id, $kode] = captchaDenganKode();
    $this->postJson('/api/auth/register', [...$data, 'captcha_id' => $id, 'captcha' => $kode])->assertCreated();
});
