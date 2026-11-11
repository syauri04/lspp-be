<?php

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\Support\MukFixture;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super-admin');
});

/** CMS: super admin klik "Buka di MUK" -> ambil kode dari fragment URL redirect. */
function madKode($test, $penilaian, ?User $user = null): string
{
    $res = $test->actingAs($user ?? $test->superAdmin)->post("/muk/{$penilaian->id}/buka")->assertRedirect();
    $lokasi = $res->headers->get('Location');
    expect($lokasi)->toStartWith(config('muk.frontend_url').'/admin/masuk#kode=');
    app('auth')->forgetGuards();

    return substr($lokasi, strpos($lokasi, '#kode=') + 6);
}

function madToken($test, $penilaian): string
{
    $kode = madKode($test, $penilaian);

    return $test->postJson('/api/muk/auth/admin/tukar', ['kode' => $kode])
        ->assertOk()
        ->assertJsonPath('data.user.role', 'admin')
        ->assertJsonPath('data.penilaian_id', $penilaian->id)
        ->json('data.token');
}

it('hanya super admin yang dapat membuat tautan; kode sekali pakai & kedaluwarsa', function () {
    $p = MukFixture::penilaian();

    $this->actingAs(User::factory()->create())->post("/muk/{$p->id}/buka")->assertForbidden();
    app('auth')->forgetGuards();

    $kode = madKode($this, $p);
    $this->postJson('/api/muk/auth/admin/tukar', ['kode' => $kode])->assertOk();
    $this->postJson('/api/muk/auth/admin/tukar', ['kode' => $kode])->assertUnprocessable(); // sudah dipakai

    $kode = madKode($this, $p);
    $this->travel(config('muk.admin_kode_ttl_seconds') + 1)->seconds();
    $this->postJson('/api/muk/auth/admin/tukar', ['kode' => $kode])->assertUnprocessable();

    $this->postJson('/api/muk/auth/admin/tukar', ['kode' => str_repeat('a', 64)])->assertUnprocessable();
    $this->postJson('/api/muk/auth/admin/tukar', ['kode' => 'pendek'])->assertUnprocessable();
});

it('token admin hanya membuka penilaian tsb, hanya dokumen terkirim, dan tidak bisa menulis', function () {
    $p = MukFixture::penilaian();
    $lain = MukFixture::penilaian();
    $token = madToken($this, $p);

    $this->withToken($token)->getJson('/api/muk/auth/me')->assertOk()->assertJsonPath('data.role', 'admin');
    $this->withToken($token)->getJson('/api/muk/penilaian')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $p->id);
    $this->withToken($token)->getJson("/api/muk/penilaian/{$lain->id}")->assertNotFound();
    $this->withToken($token)->getJson("/api/muk/penilaian/{$lain->id}/dokumen/apl-01")->assertNotFound();

    // Draft: terkunci untuk admin.
    $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/apl-01")->assertForbidden();

    MukFixture::dokumen($p, JenisDokumenMuk::APL_01)->update(['status' => DokumenPenilaian::STATUS_MENUNGGU_REVIEW]);
    $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/apl-01")
        ->assertOk()->assertJsonPath('data.dokumen.dapat_diubah', false);
    $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}")->assertOk();

    // Semua jalur tulis ditolak.
    $this->withToken($token)->patchJson("/api/muk/penilaian/{$p->id}/dokumen/apl-01", ['isian' => []])->assertForbidden();
    $this->withToken($token)->postJson("/api/muk/penilaian/{$p->id}/dokumen/apl-01/submit")->assertForbidden();
    $this->withToken($token)->postJson("/api/muk/penilaian/{$p->id}/dokumen/apl-02/review", ['hasil' => 'dapat_dilanjutkan'])->assertForbidden();
    $this->withToken($token)->postJson("/api/muk/penilaian/{$p->id}/metode", ['metode' => 'tpd'])->assertForbidden();
});

it('admin dapat membaca setiap jenis dokumen yang sudah selesai (tanpa error handler)', function () {
    $p = MukFixture::penilaian();
    $p->update(['metode_asesmen' => 'tpd']);
    foreach (JenisDokumenMuk::all() as $jenis) {
        DokumenPenilaian::updateOrCreate(
            ['penilaian_muk_id' => $p->id, 'jenis_dokumen_muk_id' => $jenis->id],
            ['status' => DokumenPenilaian::STATUS_SELESAI, 'submitted_at' => now()],
        );
    }
    $token = madToken($this, $p);

    foreach (array_keys(config('muk.dokumen_handlers')) as $slug) {
        $res = $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/{$slug}");
        expect($res->status())->toBe(200, "dokumen {$slug}: ".$res->getContent());
        expect($res->json('data.dokumen.dapat_diubah'))->toBeFalse();
    }
});

it('akses dicabut bila user bukan lagi super admin; token website/CMS tanpa ability ditolak', function () {
    $p = MukFixture::penilaian();
    $token = madToken($this, $p);

    $this->superAdmin->removeRole('super-admin');
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}")->assertForbidden();
    expect($this->superAdmin->tokens()->count())->toBe(0);

    // Token User biasa (bukan dari penukaran) tidak dapat memakai MUK.
    app('auth')->forgetGuards();
    $polos = $this->superAdmin->createToken('lain')->plainTextToken;
    $this->withToken($polos)->getJson('/api/muk/penilaian')->assertForbidden();
});
