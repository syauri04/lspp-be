<?php

use App\Models\Asesi;
use App\Models\PendaftaranSertifikasi;
use App\Notifications\PendaftaranDisetujuiNotifikasi;
use App\Notifications\PendaftaranDitolakNotifikasi;
use Illuminate\Support\Str;
use Tests\Support\MukFixture;

/** Token website lspp-fe (ability "*"), berbeda dengan token MUK. */
function tokenWebsite(Asesi $asesi): string
{
    return $asesi->createToken('auth_token')->plainTextToken;
}

function pendaftaranBaru(Asesi $asesi, string $status, int $skemaId): PendaftaranSertifikasi
{
    return PendaftaranSertifikasi::create([
        'kode_pendaftaran' => (string) Str::uuid(),
        'asesi_id' => $asesi->id,
        'skema_sertifikasi_id' => $skemaId,
        'status' => $status,
        'harga_snapshot' => 500000,
        'submitted_at' => now(),
        'catatan_admin' => $status === 'rejected' ? 'KTP buram' : null,
    ]);
}

describe('GET /api/pendaftaran-sertifikasi (profil)', function () {
    it('daftar milik asesi: kelompok, jadwal, TUK, asesor, aksi, jumlah per tab; filter kelompok', function () {
        $asesi = Asesi::factory()->create();
        $penilaian = MukFixture::penilaian(asesi: $asesi);
        $skemaId = $penilaian->pendaftaran->skema_sertifikasi_id;
        $bayar = pendaftaranBaru($asesi, 'awaiting_payment', $skemaId);
        pendaftaranBaru($asesi, 'rejected', $skemaId);
        pendaftaranBaru(Asesi::factory()->create(), 'submitted', $skemaId); // milik asesi lain
        $token = tokenWebsite($asesi);

        $res = $this->withToken($token)->getJson('/api/pendaftaran-sertifikasi')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.jumlah', ['diajukan' => 0, 'diproses' => 2, 'selesai' => 0, 'ditolak' => 1]);

        $terjadwal = collect($res->json('data'))->firstWhere('status', 'dijadwalkan');
        expect($terjadwal['kelompok'])->toBe('diproses')
            ->and($terjadwal['status_label'])->toBe('Terjadwal')
            ->and($terjadwal['jadwal']['tanggal'])->toBe('2026-11-01')
            ->and($terjadwal['jadwal']['waktu_mulai'])->toBe('08:00')
            ->and($terjadwal['jadwal']['tuk']['nama'])->toBe('TUK Uji')
            ->and($terjadwal['jadwal']['asesor'])->toBe($penilaian->jadwalAsesmen->asesor->nama)
            ->and($terjadwal['asesmen']['status'])->toBe('menunggu_pengisian_apl')
            ->and($terjadwal['aksi']['jenis'])->toBe('mulai_asesmen')
            ->and($terjadwal['aksi']['url'])->toEndWith("/dashboard/penilaian/{$penilaian->id}")
            ->and($terjadwal['skema']['judul'])->toBeArray();

        expect(collect($res->json('data'))->firstWhere('kode_pendaftaran', $bayar->kode_pendaftaran)['aksi'])
            ->toBe(['jenis' => 'bayar', 'url' => "/checkout/{$bayar->kode_pendaftaran}"]);

        $this->withToken($token)->getJson('/api/pendaftaran-sertifikasi?kelompok=ditolak')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.catatan_admin', 'KTP buram')
            ->assertJsonPath('data.0.aksi', null);
        $this->withToken($token)->getJson('/api/pendaftaran-sertifikasi?kelompok=salah')->assertJsonValidationErrors('kelompok');
    });

    it('detail berisi timeline riwayat status; asesi lain ditolak; token MUK ditolak', function () {
        $asesi = Asesi::factory()->create();
        $penilaian = MukFixture::penilaian(asesi: $asesi);
        $pendaftaran = $penilaian->pendaftaran;
        $pendaftaran->riwayatStatus()->create(['status_dari' => null, 'status_ke' => 'submitted', 'keterangan' => 'Diajukan.']);
        $pendaftaran->riwayatStatus()->create(['status_dari' => 'paid', 'status_ke' => 'dijadwalkan', 'keterangan' => 'Asesmen 1 Nov.']);

        $this->withToken(tokenWebsite($asesi))->getJson("/api/pendaftaran-sertifikasi/{$pendaftaran->kode_pendaftaran}")
            ->assertOk()
            ->assertJsonPath('data.timeline.0.judul', 'Diajukan')
            ->assertJsonPath('data.timeline.1.keterangan', 'Asesmen 1 Nov.')
            ->assertJsonPath('data.pembayaran', null);

        app('auth')->forgetGuards();
        $this->withToken(tokenWebsite(Asesi::factory()->create()))->getJson("/api/pendaftaran-sertifikasi/{$pendaftaran->kode_pendaftaran}")->assertForbidden();
        app('auth')->forgetGuards();
        $this->withToken(MukFixture::token($asesi))->getJson('/api/pendaftaran-sertifikasi')->assertForbidden();
    });
});

describe('Notifikasi asesi', function () {
    it('daftar, jumlah belum dibaca, format standar & payload lama, tandai dibaca', function () {
        $asesi = Asesi::factory()->create();
        $pendaftaran = MukFixture::penilaian(asesi: $asesi)->pendaftaran;
        $pendaftaran->update(['catatan_admin' => 'Foto buram']);

        // Disimpan lewat channel database (mail dilewati).
        $asesi->notifyNow(new PendaftaranDisetujuiNotifikasi($pendaftaran), ['database']);
        $asesi->notifyNow(new PendaftaranDitolakNotifikasi($pendaftaran), ['database']);
        // Payload lama (sebelum format standar).
        $asesi->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => PendaftaranDisetujuiNotifikasi::class,
            'data' => ['kode_pendaftaran' => 'abc', 'status' => 'awaiting_payment', 'message' => 'Pesan lama'],
        ]);
        $lain = Asesi::factory()->create();
        $lain->notifyNow(new PendaftaranDisetujuiNotifikasi($pendaftaran), ['database']);
        $token = tokenWebsite($asesi);

        $res = $this->withToken($token)->getJson('/api/notifikasi')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.belum_dibaca', 3);
        $data = collect($res->json('data'));
        $lama = $data->firstWhere('pesan', 'Pesan lama');
        expect($lama)->toMatchArray(['judul' => 'Pendaftaran disetujui', 'kategori' => 'pendaftaran', 'url' => '/checkout/abc', 'dibaca' => false]);
        $baru = $data->firstWhere('judul', 'Pendaftaran ditolak');
        expect($baru['pesan'])->toContain('Foto buram')
            ->and($baru['skema'])->toBe($pendaftaran->skemaSertifikasi->title['id']);

        // Tandai satu, lalu semua.
        $this->withToken($token)->postJson("/api/notifikasi/{$baru['id']}/baca")->assertOk()->assertJsonPath('data.dibaca', true);
        $this->withToken($token)->getJson('/api/notifikasi?belum_dibaca=1')->assertJsonCount(2, 'data')->assertJsonPath('meta.belum_dibaca', 2);
        $this->withToken($token)->postJson('/api/notifikasi/baca-semua')->assertOk()->assertJsonPath('data.jumlah', 2);
        $this->withToken($token)->getJson('/api/notifikasi')->assertJsonPath('meta.belum_dibaca', 0);

        // Notifikasi asesi lain tidak bisa disentuh.
        $milikLain = $lain->notifications()->value('id');
        $this->withToken($token)->postJson("/api/notifikasi/{$milikLain}/baca")->assertNotFound();
        expect($lain->unreadNotifications()->count())->toBe(1);

        // Token MUK ditolak.
        app('auth')->forgetGuards();
        $this->withToken(MukFixture::token($asesi))->getJson('/api/notifikasi')->assertForbidden();
    });
});
