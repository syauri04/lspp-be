<?php

use App\Models\Asesi;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\Pembayaran;
use App\Models\PendaftaranSertifikasi;
use App\Models\User;
use App\Notifications\Admin\PemberitahuanAdmin;
use App\Notifications\PendaftaranMasukNotifikasi;
use App\Services\Pembayaran\KonfirmasiPembayaran;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Support\MukFixture;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
});

function pendaftaranCms(string $status): PendaftaranSertifikasi
{
    return PendaftaranSertifikasi::create([
        'kode_pendaftaran' => (string) Str::uuid(),
        'asesi_id' => Asesi::factory()->create()->id,
        'skema_sertifikasi_id' => MukFixture::skema()->id,
        'status' => $status,
        'harga_snapshot' => 500000,
        'submitted_at' => now(),
    ]);
}

it('badge sidebar: pendaftaran submitted, lunas belum dijadwalkan, APL.01 menunggu review', function () {
    pendaftaranCms('submitted');
    pendaftaranCms('submitted');
    pendaftaranCms('paid');
    pendaftaranCms('rejected');

    $penilaian = MukFixture::penilaian(); // terjadwal -> tidak dihitung di penjadwalan
    MukFixture::dokumen($penilaian, JenisDokumenMuk::APL_01)->update(['status' => DokumenPenilaian::STATUS_MENUNGGU_REVIEW]);

    $html = $this->actingAs($this->admin)->get(route('pendaftaran.index'))->assertOk()->getContent();

    expect($html)->toContain('title="Pendaftaran perlu direview">2</span>')
        ->and($html)->toContain('title="Sudah lunas, belum dijadwalkan">1</span>')
        ->and($html)->toContain('title="FR.APL.01 menunggu review">1</span>')
        ->and($html)->not->toContain('title="Bukti transfer menunggu verifikasi"');
});

it('lonceng: tampil notifikasi admin, buka = tandai dibaca + menuju halaman terkait', function () {
    $p = pendaftaranCms('submitted');
    $this->admin->notify(new PendaftaranMasukNotifikasi($p->load('asesi', 'skemaSertifikasi')));
    $notif = $this->admin->notifications()->sole();

    $this->actingAs($this->admin)->get(route('pendaftaran.index'))
        ->assertOk()
        ->assertSee('Pendaftaran baru perlu direview')
        ->assertSee(route('notifikasi-admin.buka', $notif->id), false);

    $this->actingAs($this->admin)->get(route('notifikasi-admin.buka', $notif->id))
        ->assertRedirect(route('pendaftaran.show', $p->kode_pendaftaran));

    expect($notif->fresh()->read_at)->not->toBeNull();
});

it('admin tidak bisa membuka notifikasi admin lain; URL luar tidak diikuti', function () {
    $lain = User::factory()->create();
    $lain->notify(new PemberitahuanAdmin('lain', 'X', 'Y', '/pendaftaran'));
    $this->actingAs($this->admin)->get(route('notifikasi-admin.buka', $lain->notifications()->sole()->id))->assertNotFound();

    $this->admin->notify(new PemberitahuanAdmin('lain', 'Jebakan', 'Y', 'https://evil.example.com/phish'));
    $this->actingAs($this->admin)->get(route('notifikasi-admin.buka', $this->admin->notifications()->sole()->id))
        ->assertRedirect(route('notifikasi-admin.index'));
});

it('pembayaran lunas memberi notifikasi penjadwalan ke admin', function () {
    $p = pendaftaranCms('awaiting_payment');
    $bayar = Pembayaran::create(['kode_transaksi' => 'INV-'.Str::random(8), 'pendaftaran_sertifikasi_id' => $p->id, 'jumlah' => 500000, 'status' => 'pending']);

    app(KonfirmasiPembayaran::class)->lunas($bayar, 'Lunas uji.', 'QRIS');

    $n = $this->admin->notifications()->where('type', PemberitahuanAdmin::class)->sole();
    expect($n->data['kategori'])->toBe('penjadwalan')
        ->and($n->data['url'])->toBe(route('penjadwalan-asesmen.create', $p->kode_pendaftaran))
        ->and($n->data['pesan'])->toContain('(QRIS)');
});

it('halaman semua notifikasi, filter belum dibaca & tandai semua dibaca', function () {
    $this->admin->notify(new PemberitahuanAdmin('asesmen', 'Asesmen selesai', 'Budi selesai.', '/asesmen'));
    $this->admin->notify(new PemberitahuanAdmin('penjadwalan', 'Perlu dijadwalkan', 'Ani lunas.', '/penjadwalan-asesmen'));

    $this->actingAs($this->admin)->get(route('notifikasi-admin.index', ['filter' => 'belum']))
        ->assertOk()->assertSee('Asesmen selesai')->assertSee('Perlu dijadwalkan');

    $this->actingAs($this->admin)->post(route('notifikasi-admin.baca-semua'))->assertRedirect();
    expect($this->admin->unreadNotifications()->count())->toBe(0);

    $this->actingAs($this->admin)->get(route('notifikasi-admin.index', ['filter' => 'belum']))
        ->assertOk()->assertSee('Tidak ada notifikasi yang belum dibaca.');
});
