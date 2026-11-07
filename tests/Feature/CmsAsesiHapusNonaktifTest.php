<?php

use App\Models\Asesi;
use App\Models\PendaftaranSertifikasi;
use App\Models\PenilaianMuk;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\Support\MukFixture;

beforeEach(function () {
    Cache::flush();
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
});

describe('hapus asesi di CMS', function () {
    it('asesi tanpa pendaftaran terhapus beserta token & notifikasinya', function () {
        $asesi = Asesi::factory()->create();
        $asesi->createToken('auth_token');
        $asesi->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'x', 'data' => []]);

        $this->actingAs($this->admin)->delete(route('asesis.destroy', $asesi->id))
            ->assertRedirect(route('asesis.index'))
            ->assertSessionHas('success');

        expect(Asesi::find($asesi->id))->toBeNull()
            ->and($asesi->tokens()->count())->toBe(0)
            ->and($asesi->notifications()->count())->toBe(0);
    });

    it('asesi yang sudah terjadwal ditolak, semua data tetap utuh', function () {
        $penilaian = MukFixture::penilaian();
        $asesi = $penilaian->pendaftaran->asesi;

        $this->actingAs($this->admin)->delete(route('asesis.destroy', $asesi->id))
            ->assertRedirect(route('asesis.index'))
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, '1 pendaftaran'));

        expect(Asesi::find($asesi->id))->not->toBeNull()
            ->and(PendaftaranSertifikasi::where('asesi_id', $asesi->id)->count())->toBe(1)
            ->and(PenilaianMuk::find($penilaian->id))->not->toBeNull();
    });

    it('FK database menolak hapus langsung asesi yang punya pendaftaran', function () {
        $asesi = MukFixture::penilaian()->pendaftaran->asesi;

        expect(fn () => $asesi->delete())->toThrow(QueryException::class);
    });

    it('daftar asesi menampilkan jumlah pendaftaran & status nonaktif', function () {
        $asesi = MukFixture::penilaian()->pendaftaran->asesi;
        $asesi->forceFill(['nonaktif_at' => now()])->save();

        $this->actingAs($this->admin)->get(route('asesis.index'))
            ->assertOk()
            ->assertSee('1 pendaftaran')
            ->assertSee('Nonaktif')
            ->assertSee(route('asesis.aktifkan', $asesi->id));
    });
});

describe('nonaktifkan asesi', function () {
    it('mencabut semua token dan menolak login website, Google, dan MUK', function () {
        $asesi = Asesi::factory()->create(['password' => 'rahasia123']);
        $asesi->createToken('auth_token');
        $asesi->createToken(config('muk.token_name'), ['muk:asesi']);

        $this->actingAs($this->admin)->patch(route('asesis.nonaktifkan', $asesi->id))
            ->assertSessionHas('success');

        expect($asesi->fresh()->nonaktif())->toBeTrue()
            ->and($asesi->tokens()->count())->toBe(0);

        $this->postJson('/api/auth/login', ['email' => $asesi->email, 'password' => 'rahasia123'])
            ->assertStatus(422)
            ->assertJsonPath('message', Asesi::PESAN_NONAKTIF);

        $this->postJson('/api/muk/auth/asesi/login', ['email' => $asesi->email, 'password' => 'rahasia123'])
            ->assertStatus(422)
            ->assertJsonPath('message', Asesi::PESAN_NONAKTIF);

        expect($asesi->tokens()->count())->toBe(0);
    });

    it('diaktifkan kembali bisa login lagi', function () {
        $asesi = Asesi::factory()->create(['password' => 'rahasia123', 'email_verified_at' => now()]);
        $asesi->forceFill(['nonaktif_at' => now()])->save();

        $this->actingAs($this->admin)->patch(route('asesis.aktifkan', $asesi->id))
            ->assertSessionHas('success');

        expect($asesi->fresh()->nonaktif())->toBeFalse();

        $this->postJson('/api/auth/login', ['email' => $asesi->email, 'password' => 'rahasia123'])
            ->assertOk();
    });
});
