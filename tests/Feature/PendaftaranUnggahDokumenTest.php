<?php

use App\Models\Asesi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Support\MukFixture;

/** Payload pendaftaran lengkap; $ukuranKb per field (bawaan 100 KB). */
function payloadPendaftaran(int $skemaId, array $ukuranKb = []): array
{
    $kb = fn (string $f) => $ukuranKb[$f] ?? 100;

    return [
        'skema_sertifikasi_id' => $skemaId,
        'ktp' => UploadedFile::fake()->create('ktp.pdf', $kb('ktp'), 'application/pdf'),
        'ijazah' => UploadedFile::fake()->create('ijazah.pdf', $kb('ijazah'), 'application/pdf'),
        'portfolio' => UploadedFile::fake()->create('portofolio.pdf', $kb('portfolio'), 'application/pdf'),
        'cv' => UploadedFile::fake()->create('cv.pdf', $kb('cv'), 'application/pdf'),
        'pas_foto' => UploadedFile::fake()->create('foto.jpg', $kb('pas_foto'), 'image/jpeg'),
    ];
}

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    foreach (['admin', 'super-admin'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('menerima dokumen sampai 25 MB per file', function () {
    $asesi = Asesi::factory()->create();
    $skema = MukFixture::skema();
    $token = $asesi->createToken('auth_token')->plainTextToken;

    $this->withToken($token)
        ->post('/api/pendaftaran-sertifikasi', payloadPendaftaran($skema->id, [
            'ktp' => 25600, 'pas_foto' => 25600, 'portfolio' => 20000,
        ]), ['Accept' => 'application/json'])
        ->assertCreated();
});

it('menolak file > 25 MB dengan pesan per file', function () {
    $asesi = Asesi::factory()->create();
    $skema = MukFixture::skema();
    $token = $asesi->createToken('auth_token')->plainTextToken;

    $res = $this->withToken($token)
        ->post('/api/pendaftaran-sertifikasi', payloadPendaftaran($skema->id, [
            'ktp' => 25601, 'pas_foto' => 30000,
        ]), ['Accept' => 'application/json'])
        ->assertStatus(422);

    expect($res->json('errors.ktp.0'))->toBe('Ukuran KTP maksimal 25 MB.')
        ->and($res->json('errors.pas_foto.0'))->toBe('Ukuran Pas foto maksimal 25 MB.')
        ->and($res->json('errors'))->not->toHaveKeys(['ijazah', 'cv', 'portfolio']);
});

it('pesan format per file', function () {
    $asesi = Asesi::factory()->create();
    $skema = MukFixture::skema();
    $token = $asesi->createToken('auth_token')->plainTextToken;

    $payload = payloadPendaftaran($skema->id);
    $payload['cv'] = UploadedFile::fake()->create('cv.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    unset($payload['ijazah']);

    $res = $this->withToken($token)
        ->post('/api/pendaftaran-sertifikasi', $payload, ['Accept' => 'application/json'])
        ->assertStatus(422);

    expect($res->json('errors.cv.0'))->toBe('CV wajib berformat PDF.')
        ->and($res->json('errors.ijazah.0'))->toBe('Ijazah wajib diunggah.');
});
