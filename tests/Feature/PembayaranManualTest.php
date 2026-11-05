<?php

use App\Models\Asesi;
use App\Models\BuktiPembayaran;
use App\Models\Pembayaran;
use App\Models\PendaftaranSertifikasi;
use App\Models\Pengaturan;
use App\Models\RekeningPembayaran;
use App\Models\User;
use App\Notifications\BuktiPembayaranDitolakNotifikasi;
use App\Notifications\BuktiPembayaranMasukNotifikasi;
use App\Notifications\StatusPendaftaranNotifikasi;
use App\Services\Pembayaran\KonfirmasiPembayaran;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Support\MukFixture;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    Cache::flush();
    $this->rekening = RekeningPembayaran::create(['nama_bank' => 'Bank Mandiri', 'nomor_rekening' => '123-000-4567', 'atas_nama' => 'LSP Pariwisata 306']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
});

/** Pendaftaran menunggu pembayaran + tagihan pending (dibuat kemarin). */
function tagihanManual(): PendaftaranSertifikasi
{
    $p = PendaftaranSertifikasi::create([
        'kode_pendaftaran' => (string) Str::uuid(),
        'asesi_id' => Asesi::factory()->create()->id,
        'skema_sertifikasi_id' => MukFixture::skema()->id,
        'status' => 'awaiting_payment',
        'harga_snapshot' => 750000,
        'submitted_at' => now(),
    ]);
    $bayar = Pembayaran::create(['kode_transaksi' => 'LSPP-'.Str::random(8), 'pendaftaran_sertifikasi_id' => $p->id, 'jumlah' => 750000, 'status' => 'pending']);
    $bayar->forceFill(['created_at' => now()->subDay()])->save();

    return $p->load('pembayaran', 'asesi');
}

function formBukti(int $rekeningId, array $ubah = []): array
{
    return [
        'rekening_pembayaran_id' => $rekeningId,
        'nama_pengirim' => 'Budi Santoso',
        'bank_pengirim' => 'BCA',
        'tanggal_transfer' => now('Asia/Jakarta')->toDateString(),
        'jumlah_transfer' => 750000,
        'file' => UploadedFile::fake()->image('bukti.jpg', 600, 800),
        ...$ubah,
    ];
}

function tokenAsesi(PendaftaranSertifikasi $p): string
{
    return $p->asesi->createToken('auth_token')->plainTextToken;
}

describe('checkout asesi', function () {
    it('menampilkan metode & rekening aktif; transfer manual disembunyikan bila tidak ada rekening', function () {
        $p = tagihanManual();
        RekeningPembayaran::create(['nama_bank' => 'BRI', 'nomor_rekening' => '999', 'atas_nama' => 'X', 'aktif' => false]);

        $this->withToken(tokenAsesi($p))->getJson("/api/checkout/{$p->kode_pendaftaran}")->assertOk()
            ->assertJsonPath('data.metode', ['doku' => true, 'manual' => true])
            ->assertJsonCount(1, 'data.rekening')
            ->assertJsonPath('data.rekening.0.nama_bank', 'Bank Mandiri')
            ->assertJsonPath('data.bukti_terakhir', null);

        $this->rekening->update(['aktif' => false]);
        $this->withToken(tokenAsesi($p))->getJson("/api/checkout/{$p->kode_pendaftaran}")
            ->assertJsonPath('data.metode.manual', false)->assertJsonCount(0, 'data.rekening');
    });

    it('unggah bukti: validasi, tersimpan privat, riwayat, admin dikabari; tidak bisa ganda; DOKU diblokir', function () {
        $p = tagihanManual();
        $token = tokenAsesi($p);
        $url = "/api/checkout/{$p->kode_pendaftaran}/bukti-transfer";

        $this->withToken($token)->post($url, formBukti($this->rekening->id, [
            'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
            'tanggal_transfer' => now('Asia/Jakarta')->addDay()->toDateString(),
        ]), ['Accept' => 'application/json'])->assertJsonValidationErrors(['file', 'tanggal_transfer']);
        $this->withToken($token)->post($url, formBukti(999999), ['Accept' => 'application/json'])->assertJsonValidationErrors('rekening_pembayaran_id');

        $this->withToken($token)->post($url, formBukti($this->rekening->id), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'menunggu')
            ->assertJsonPath('data.rekening_tujuan.nomor_rekening', '123-000-4567')
            ->assertJsonMissingPath('data.path_file');

        $bukti = BuktiPembayaran::sole();
        Storage::disk('local')->assertExists($bukti->path_file);
        expect($bukti->path_file)->toStartWith("bukti-pembayaran/{$p->kode_pendaftaran}/")
            ->and($p->fresh()->status)->toBe('awaiting_payment')
            ->and($p->riwayatStatus()->where('keterangan', 'like', 'Bukti transfer%')->exists())->toBeTrue();
        Notification::assertSentTo($this->admin, BuktiPembayaranMasukNotifikasi::class);

        // Bukti masih menunggu: unggah ulang & bayar DOKU ditolak.
        $this->withToken($token)->post($url, formBukti($this->rekening->id), ['Accept' => 'application/json'])->assertStatus(409);
        $this->withToken($token)->postJson("/api/checkout/{$p->kode_pendaftaran}/pay")->assertStatus(409);
        $this->withToken($token)->getJson("/api/checkout/{$p->kode_pendaftaran}")->assertJsonPath('data.bukti_terakhir.status', 'menunggu');
        $this->withToken($token)->getJson('/api/pendaftaran-sertifikasi')
            ->assertJsonPath('data.0.status_label', 'Menunggu verifikasi pembayaran')
            ->assertJsonPath('data.0.aksi.jenis', 'cek_pembayaran');
    });

    it('asesi lain, token MUK, & transfer manual nonaktif ditolak', function () {
        $p = tagihanManual();
        $url = "/api/checkout/{$p->kode_pendaftaran}/bukti-transfer";

        $lain = Asesi::factory()->create()->createToken('auth_token')->plainTextToken;
        $this->withToken($lain)->post($url, formBukti($this->rekening->id), ['Accept' => 'application/json'])->assertForbidden();
        app('auth')->forgetGuards();
        $this->withToken(MukFixture::token($p->asesi))->post($url, formBukti($this->rekening->id), ['Accept' => 'application/json'])->assertForbidden();
        app('auth')->forgetGuards();

        Pengaturan::simpan(Pengaturan::PEMBAYARAN_MANUAL_AKTIF, false);
        $this->withToken(tokenAsesi($p))->post($url, formBukti($this->rekening->id), ['Accept' => 'application/json'])->assertStatus(409);
        expect(BuktiPembayaran::count())->toBe(0);
        Storage::disk('local')->assertDirectoryEmpty("bukti-pembayaran/{$p->kode_pendaftaran}");
    });

    it('DOKU dimatikan di CMS -> bayar online ditolak', function () {
        $p = tagihanManual();
        Pengaturan::simpan(Pengaturan::PEMBAYARAN_DOKU_AKTIF, false);

        $this->withToken(tokenAsesi($p))->getJson("/api/checkout/{$p->kode_pendaftaran}")->assertJsonPath('data.metode.doku', false);
        $this->withToken(tokenAsesi($p))->postJson("/api/checkout/{$p->kode_pendaftaran}/pay")
            ->assertStatus(409)->assertJsonPath('message', 'Pembayaran online sedang tidak tersedia. Silakan gunakan transfer manual.');
    });
});

describe('verifikasi admin (CMS)', function () {
    it('setujui: tagihan lunas TRANSFER_MANUAL, siap dijadwalkan, asesi dikabari; tidak bisa disetujui dua kali', function () {
        $p = tagihanManual();
        $this->withToken(tokenAsesi($p))->post("/api/checkout/{$p->kode_pendaftaran}/bukti-transfer", formBukti($this->rekening->id), ['Accept' => 'application/json'])->assertCreated();
        $bukti = BuktiPembayaran::sole();
        app('auth')->forgetGuards();

        $this->actingAs($this->admin)->get(route('verifikasi-pembayaran.index'))->assertOk()->assertSee($p->asesi->name);
        $this->actingAs($this->admin)->get(route('verifikasi-pembayaran.show', $bukti))->assertOk()->assertSee('Sesuai tagihan');
        $this->actingAs($this->admin)->get(route('verifikasi-pembayaran.berkas', $bukti))->assertOk();

        $this->actingAs($this->admin)->post(route('verifikasi-pembayaran.setujui', $bukti))->assertRedirect(route('verifikasi-pembayaran.index'));

        $bayar = $p->pembayaran->fresh();
        expect($bayar->status)->toBe('paid')
            ->and($bayar->metode_pembayaran)->toBe('TRANSFER_MANUAL')
            ->and($p->fresh()->status)->toBe('paid')
            ->and($bukti->fresh()->status)->toBe('disetujui')
            ->and($bukti->fresh()->diverifikasi_oleh)->toBe($this->admin->id)
            ->and($p->riwayatStatus()->where('status_ke', 'paid')->value('changed_by'))->toBe($this->admin->id);
        Notification::assertSentTo($p->asesi, StatusPendaftaranNotifikasi::class, fn ($n) => $n->status === 'paid');
        // Muncul di daftar penjadwalan asesmen (status paid).
        $this->actingAs($this->admin)->get(route('penjadwalan-asesmen.index'))->assertOk()->assertSee($p->asesi->name);

        $this->actingAs($this->admin)->post(route('verifikasi-pembayaran.setujui', $bukti))->assertSessionHas('error');
    });

    it('tolak: alasan wajib, asesi dikabari, lalu bisa unggah ulang', function () {
        $p = tagihanManual();
        $url = "/api/checkout/{$p->kode_pendaftaran}/bukti-transfer";
        $this->withToken(tokenAsesi($p))->post($url, formBukti($this->rekening->id), ['Accept' => 'application/json'])->assertCreated();
        $bukti = BuktiPembayaran::sole();
        app('auth')->forgetGuards();

        $this->actingAs($this->admin)->post(route('verifikasi-pembayaran.tolak', $bukti), ['alasan' => ''])->assertSessionHasErrors('alasan');
        $this->actingAs($this->admin)->post(route('verifikasi-pembayaran.tolak', $bukti), ['alasan' => 'Dana belum masuk ke rekening.'])
            ->assertRedirect(route('verifikasi-pembayaran.index'));

        expect($bukti->fresh()->status)->toBe('ditolak')->and($p->fresh()->status)->toBe('awaiting_payment');
        Notification::assertSentTo($p->asesi, BuktiPembayaranDitolakNotifikasi::class, fn ($n) => str_contains($n->toArray($p->asesi)['pesan'], 'Dana belum masuk'));

        app('auth')->forgetGuards();
        $this->withToken(tokenAsesi($p))->getJson("/api/checkout/{$p->kode_pendaftaran}")
            ->assertJsonPath('data.bukti_terakhir.status', 'ditolak')
            ->assertJsonPath('data.bukti_terakhir.catatan_admin', 'Dana belum masuk ke rekening.');
        $this->withToken(tokenAsesi($p))->post($url, formBukti($this->rekening->id), ['Accept' => 'application/json'])->assertCreated();
        expect(BuktiPembayaran::where('status', 'menunggu')->count())->toBe(1);
    });

    it('lunas lewat DOKU saat bukti masih menunggu -> bukti dibatalkan otomatis', function () {
        $p = tagihanManual();
        $this->withToken(tokenAsesi($p))->post("/api/checkout/{$p->kode_pendaftaran}/bukti-transfer", formBukti($this->rekening->id), ['Accept' => 'application/json'])->assertCreated();

        app(KonfirmasiPembayaran::class)->lunas($p->pembayaran, 'Webhook DOKU', 'VIRTUAL_ACCOUNT_BCA');

        expect(BuktiPembayaran::sole()->status)->toBe('dibatalkan');
        $this->actingAs($this->admin)->post(route('verifikasi-pembayaran.setujui', BuktiPembayaran::sole()))->assertSessionHas('error');
    });

    it('berkas & halaman verifikasi hanya untuk admin login', function () {
        $p = tagihanManual();
        $this->withToken(tokenAsesi($p))->post("/api/checkout/{$p->kode_pendaftaran}/bukti-transfer", formBukti($this->rekening->id), ['Accept' => 'application/json'])->assertCreated();
        // Request CMS berikutnya tanpa token & dengan guard web (sesi), seperti browser admin.
        app('auth')->forgetGuards();
        app('auth')->shouldUse('web');
        $this->withoutToken();

        $this->get(route('verifikasi-pembayaran.berkas', BuktiPembayaran::sole()))->assertRedirect();
        // Token asesi tidak berlaku di CMS.
        $this->withToken(tokenAsesi($p))->get(route('verifikasi-pembayaran.berkas', BuktiPembayaran::sole()))->assertRedirect();
        $this->get(route('verifikasi-pembayaran.index'))->assertRedirect();
    });
});

describe('pengaturan pembayaran (CMS)', function () {
    it('saklar metode: minimal satu aktif; transfer manual butuh rekening aktif', function () {
        $this->actingAs($this->admin)->get(route('pengaturan-pembayaran.index'))->assertOk()->assertSee('123-000-4567');

        $this->actingAs($this->admin)->put(route('pengaturan-pembayaran.metode'), [])->assertSessionHas('error');
        $this->actingAs($this->admin)->put(route('pengaturan-pembayaran.metode'), ['manual_aktif' => 1])->assertSessionHas('success');
        expect(Pengaturan::aktif(Pengaturan::PEMBAYARAN_DOKU_AKTIF))->toBeFalse()
            ->and(Pengaturan::aktif(Pengaturan::PEMBAYARAN_MANUAL_AKTIF))->toBeTrue();

        // Rekening aktif terakhir tidak bisa dinonaktifkan / dihapus selama transfer manual aktif.
        $this->actingAs($this->admin)->put(route('pengaturan-pembayaran.rekening.update', $this->rekening), [
            'nama_bank' => 'Bank Mandiri', 'nomor_rekening' => '123-000-4567', 'atas_nama' => 'LSP Pariwisata 306',
        ])->assertSessionHas('error');
        $this->actingAs($this->admin)->delete(route('pengaturan-pembayaran.rekening.destroy', $this->rekening))->assertSessionHas('error');
        expect($this->rekening->fresh()->aktif)->toBeTrue();
    });

    it('tambah, ubah & hapus rekening; nomor rekening divalidasi', function () {
        $this->actingAs($this->admin)->post(route('pengaturan-pembayaran.rekening.store'), [
            'nama_bank' => 'BNI', 'nomor_rekening' => '<script>', 'atas_nama' => 'LSP',
        ])->assertSessionHasErrors('nomor_rekening');

        $this->actingAs($this->admin)->post(route('pengaturan-pembayaran.rekening.store'), [
            'nama_bank' => 'BNI', 'nomor_rekening' => '0123 4567 89', 'atas_nama' => 'LSP Pariwisata 306', 'aktif' => 1, 'urutan' => 2,
        ])->assertRedirect(route('pengaturan-pembayaran.index'));
        $bni = RekeningPembayaran::where('nama_bank', 'BNI')->sole();

        $this->actingAs($this->admin)->put(route('pengaturan-pembayaran.rekening.update', $bni), [
            'nama_bank' => 'BNI Syariah', 'nomor_rekening' => '0123 4567 89', 'atas_nama' => 'LSP Pariwisata 306',
        ])->assertRedirect();
        expect($bni->fresh()->nama_bank)->toBe('BNI Syariah')->and($bni->fresh()->aktif)->toBeFalse();

        $this->actingAs($this->admin)->delete(route('pengaturan-pembayaran.rekening.destroy', $bni))->assertSessionHas('success');
        expect(RekeningPembayaran::find($bni->id))->toBeNull();
    });
});
