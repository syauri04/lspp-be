<?php

use App\Models\PenerbitanSertifikasi;
use App\Models\PenilaianMuk;
use App\Models\Sertifikat;
use App\Models\User;
use App\Notifications\SertifikatAkanKedaluwarsaNotifikasi;
use App\Services\Muk\SinkronStatusAsesmen;
use App\Services\Sertifikasi\PenerbitanSertifikasiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Support\MukFixture;

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2029-07-01 08:00:00');
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super-admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->bukanAdmin = User::factory()->create();
});

/** E-sertifikat terbit (penerbitan selesai) dengan tanggal kedaluwarsa tertentu. */
function pkSertifikat(string $expired, string $status = Sertifikat::BERLAKU, string $penerbitan = PenerbitanSertifikasi::SELESAI): Sertifikat
{
    static $n = 0;
    $n++;
    $p = MukFixture::penilaian();
    $p->update(['status' => PenilaianMuk::STATUS_SELESAI_KOMPETEN, 'selesai_at' => now()]);
    DB::transaction(fn () => app(SinkronStatusAsesmen::class)->selesai($p->fresh()));
    $pn = PenerbitanSertifikasi::where('pendaftaran_sertifikasi_id', $p->pendaftaran_sertifikasi_id)->firstOrFail();
    $pn->update(['status' => $penerbitan]);

    return $pn->sertifikat()->create([
        'nama_pemegang' => "Pemegang {$n}",
        'skema_judul' => ['id' => 'Skema Uji', 'en' => 'Test Scheme'],
        'skema_nomor' => 'SKM-01',
        'unit_kompetensi' => [],
        'no_blanko' => "B{$n}",
        'no_blanko_normal' => "B{$n}",
        'no_sertifikat' => "S{$n}",
        'no_sertifikat_normal' => "S{$n}",
        'tanggal_aktif' => '2026-07-20',
        'tanggal_expired' => $expired,
        'status' => $status,
        'path_file' => 'x.pdf',
        'nama_file_asli' => 'x.pdf',
        'ukuran_file' => 1,
    ]);
}

it('mengirim pengingat H-1 bulan ke asesi & admin, sekali saja, hanya untuk sertifikat berlaku yang sudah terbit', function () {
    $tepat = pkSertifikat('2029-08-01');            // tepat 1 bulan lagi
    $dekat = pkSertifikat('2029-07-10');            // < 1 bulan (mis. cron sempat mati) -> tetap dikejar
    $jauh = pkSertifikat('2029-08-02');             // > 1 bulan -> belum
    $lewat = pkSertifikat('2029-06-30');            // sudah kedaluwarsa -> tidak
    $dicabut = pkSertifikat('2029-07-15', Sertifikat::TIDAK_BERLAKU);
    $belumTerbit = pkSertifikat('2029-07-15', penerbitan: PenerbitanSertifikasi::SIAP_DISELESAIKAN);

    $this->artisan('sertifikat:kirim-pengingat-kedaluwarsa')->expectsOutput('2 pengingat e-sertifikat kedaluwarsa dikirim.')->assertSuccessful();

    foreach ([$tepat, $dekat] as $s) {
        Notification::assertSentTo($s->penerbitan->pendaftaran->asesi, SertifikatAkanKedaluwarsaNotifikasi::class,
            fn ($n, $via) => $n->sertifikat->is($s) && $via === ['mail', 'database']);
        expect($s->fresh()->pengingat_kedaluwarsa_at)->not->toBeNull();
    }
    foreach ([$jauh, $lewat, $dicabut, $belumTerbit] as $s) {
        Notification::assertNotSentTo($s->penerbitan->pendaftaran->asesi, SertifikatAkanKedaluwarsaNotifikasi::class);
    }
    Notification::assertSentToTimes($this->superAdmin, SertifikatAkanKedaluwarsaNotifikasi::class, 2);
    Notification::assertSentToTimes($this->admin, SertifikatAkanKedaluwarsaNotifikasi::class, 2);
    Notification::assertNotSentTo($this->bukanAdmin, SertifikatAkanKedaluwarsaNotifikasi::class);

    // Dijalankan ulang (hari yang sama / besok): tidak dobel; yang "jauh" masuk jendela keesokan harinya.
    $this->artisan('sertifikat:kirim-pengingat-kedaluwarsa')->expectsOutput('0 pengingat e-sertifikat kedaluwarsa dikirim.');
    $this->travelTo('2029-07-02 08:00:00');
    $this->artisan('sertifikat:kirim-pengingat-kedaluwarsa')->expectsOutput('1 pengingat e-sertifikat kedaluwarsa dikirim.');
    Notification::assertSentToTimes($this->superAdmin, SertifikatAkanKedaluwarsaNotifikasi::class, 3);
});

it('isi email & lonceng: asesi ke profil, admin ke halaman penerbitan', function () {
    $s = pkSertifikat('2029-07-20');
    $n = new SertifikatAkanKedaluwarsaNotifikasi($s);
    $asesi = $s->penerbitan->pendaftaran->asesi;

    $mailAsesi = $n->toMail($asesi)->render();
    expect((string) $mailAsesi)->toContain('20 Juli 2029')->toContain($s->no_sertifikat)
        ->and($n->toArray($asesi))->toMatchArray(['kategori' => 'sertifikat', 'url' => rtrim(config('app.frontend_url'), '/').'/id/profile']);

    expect((string) $n->toMail($this->superAdmin)->render())->toContain($s->nama_pemegang)->toContain($s->no_blanko)
        ->and($n->toArray($this->superAdmin)['url'])->toBe("/penerbitan-sertifikat/{$s->penerbitan_sertifikasi_id}");
});

it('tanggal kedaluwarsa diubah di CMS -> pengingat dapat dikirim lagi untuk tanggal baru', function () {
    $s = pkSertifikat('2029-07-20');
    $this->artisan('sertifikat:kirim-pengingat-kedaluwarsa');
    expect($s->fresh()->pengingat_kedaluwarsa_at)->not->toBeNull();

    $data = fn (string $exp) => [
        'nama_pemegang' => $s->nama_pemegang, 'no_blanko' => $s->no_blanko, 'no_sertifikat' => $s->no_sertifikat,
        'tanggal_aktif' => '2026-07-20', 'tanggal_expired' => $exp,
    ];
    $layanan = app(PenerbitanSertifikasiService::class);

    // Ubah selain tanggal kedaluwarsa: penanda tetap.
    $layanan->simpanSertifikat($s->penerbitan, [...$data('2029-07-20'), 'nama_pemegang' => 'Nama Baru'], null, $this->superAdmin, 'Perbaikan nama');
    expect($s->fresh()->pengingat_kedaluwarsa_at)->not->toBeNull();

    $layanan->simpanSertifikat($s->penerbitan, $data('2029-07-25'), null, $this->superAdmin, 'Perbaikan tanggal');
    expect($s->fresh()->pengingat_kedaluwarsa_at)->toBeNull();
    $this->artisan('sertifikat:kirim-pengingat-kedaluwarsa')->expectsOutput('1 pengingat e-sertifikat kedaluwarsa dikirim.');
});
