<?php

use App\Models\Asesi;
use App\Models\BuktiPembayaran;
use App\Models\Pembayaran;
use App\Models\PendaftaranSertifikasi;
use App\Models\RekeningPembayaran;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherPemakaian;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Support\MukFixture;

beforeEach(function () {
    Notification::fake();
    config(['services.doku.client_id' => 'BRN-TEST', 'services.doku.secret_key' => 'SK-TEST']);
});

/** Tagihan pending Rp 1.000.000 milik asesi baru (atau asesi tertentu). */
function tagihanVoucher(?Asesi $asesi = null, int $harga = 1000000): PendaftaranSertifikasi
{
    $p = PendaftaranSertifikasi::create([
        'kode_pendaftaran' => (string) Str::uuid(),
        'asesi_id' => ($asesi ?? Asesi::factory()->create())->id,
        'skema_sertifikasi_id' => MukFixture::skema()->id,
        'status' => 'awaiting_payment',
        'harga_snapshot' => $harga,
        'submitted_at' => now(),
    ]);
    Pembayaran::create([
        'kode_transaksi' => 'INV-'.Str::random(10),
        'pendaftaran_sertifikasi_id' => $p->id,
        'jumlah' => $harga,
        'jumlah_awal' => $harga,
        'status' => 'pending',
        'expired_at' => now()->addDay(),
    ]);

    return $p->load('pembayaran', 'asesi');
}

function voucherUji(array $ubah = []): Voucher
{
    return Voucher::create([
        'kode' => 'HEMAT100',
        'tipe' => Voucher::NOMINAL,
        'nilai' => 100000,
        'mulai_at' => now()->subDay(),
        'berakhir_at' => now()->addDay(),
        'aktif' => true,
        'maks_per_asesi' => 1,
        ...$ubah,
    ]);
}

function pasangVoucherUji($test, PendaftaranSertifikasi $p, string $kode)
{
    // Guard sanctum menyimpan user dari request sebelumnya dalam satu test; reset agar token asesi ini dipakai.
    app('auth')->forgetGuards();

    return $test->withToken($p->asesi->createToken('auth_token')->plainTextToken)
        ->postJson("/api/checkout/{$p->kode_pendaftaran}/voucher", ['kode' => $kode]);
}

function webhookDokuVoucher($test, array $payload)
{
    $body = json_encode($payload);
    $h = ['Client-Id' => 'BRN-TEST', 'Request-Id' => (string) Str::uuid(), 'Request-Timestamp' => now()->utc()->format('Y-m-d\TH:i:s\Z')];
    $komponen = implode("\n", [
        'Client-Id:'.$h['Client-Id'], 'Request-Id:'.$h['Request-Id'], 'Request-Timestamp:'.$h['Request-Timestamp'],
        'Request-Target:/api/doku/callback', 'Digest:'.base64_encode(hash('sha256', $body, true)),
    ]);
    $h['Signature'] = 'HMACSHA256='.base64_encode(hash_hmac('sha256', $komponen, 'SK-TEST', true));

    return $test->call('POST', '/api/doku/callback', [], [], [], collect($h)
        ->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])
        ->merge(['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'])->all(), $body);
}

describe('pasang & lepas voucher di checkout', function () {
    it('nominal: total berkurang, tercatat per asesi & skema, tampil di GET checkout', function () {
        voucherUji();
        $p = tagihanVoucher();

        pasangVoucherUji($this, $p, ' hemat100 ')
            ->assertOk()
            ->assertJsonPath('data.jumlah', '900000.00')
            ->assertJsonPath('data.potongan', '100000.00')
            ->assertJsonPath('data.harga_awal', '1000000.00')
            ->assertJsonPath('data.voucher.kode', 'HEMAT100');

        $pakai = VoucherPemakaian::sole();
        expect($pakai->asesi_id)->toBe($p->asesi_id)
            ->and($pakai->skema_sertifikasi_id)->toBe($p->skema_sertifikasi_id)
            ->and((int) $pakai->potongan)->toBe(100000)
            ->and($p->pembayaran->fresh()->voucher_kode)->toBe('HEMAT100');

        $this->getJson("/api/checkout/{$p->kode_pendaftaran}")
            ->assertOk()
            ->assertJsonPath('data.jumlah', '900000.00')
            ->assertJsonPath('data.voucher.kode', 'HEMAT100')
            ->assertJsonPath('data.voucher_terkunci', null);
    });

    it('persentase dengan batas maksimal potongan', function () {
        voucherUji(['kode' => 'DISKON30', 'tipe' => Voucher::PERSENTASE, 'nilai' => 30, 'maks_potongan' => 250000]);
        $p = tagihanVoucher();

        pasangVoucherUji($this, $p, 'DISKON30')->assertOk()->assertJsonPath('data.jumlah', '750000.00');
    });

    it('menolak kode tidak valid, nonaktif, belum berlaku, kedaluwarsa', function (array $ubah, string $pesan, string $kode = 'HEMAT100') {
        voucherUji($ubah);
        $p = tagihanVoucher();

        pasangVoucherUji($this, $p, $kode)
            ->assertStatus(422)
            ->assertJsonPath('errors.kode.0', fn ($m) => str_contains($m, $pesan));

        expect((int) $p->pembayaran->fresh()->jumlah)->toBe(1000000);
    })->with([
        'tidak ada' => [[], 'tidak valid', 'TIDAKADA'],
        'nonaktif' => [['aktif' => false], 'tidak valid'],
        'belum berlaku' => [['mulai_at' => now()->addDay(), 'berakhir_at' => now()->addDays(2)], 'belum dapat digunakan'],
        'kedaluwarsa' => [['mulai_at' => now()->subDays(3), 'berakhir_at' => now()->subDay()], 'kedaluwarsa'],
    ]);

    it('kuota habis; tagihan kedaluwarsa mengembalikan kuota', function () {
        voucherUji(['kuota' => 1]);
        $a = tagihanVoucher();
        $b = tagihanVoucher();

        pasangVoucherUji($this, $a, 'HEMAT100')->assertOk();
        pasangVoucherUji($this, $b, 'HEMAT100')->assertStatus(422)->assertJsonPath('errors.kode.0', 'Kuota voucher sudah habis.');

        $a->pembayaran->update(['status' => 'expired']);
        pasangVoucherUji($this, $b, 'HEMAT100')->assertOk();
    });

    it('batas per asesi berlaku lintas tagihan', function () {
        voucherUji();
        $asesi = Asesi::factory()->create();
        $pertama = tagihanVoucher($asesi);
        pasangVoucherUji($this, $pertama, 'HEMAT100')->assertOk();
        $pertama->pembayaran->update(['status' => 'paid']);

        pasangVoucherUji($this, tagihanVoucher($asesi), 'HEMAT100')
            ->assertStatus(422)
            ->assertJsonPath('errors.kode.0', 'Anda sudah mencapai batas pemakaian voucher ini.');
    });

    it('ganti voucher melepas yang lama; lepas mengembalikan harga', function () {
        voucherUji();
        voucherUji(['kode' => 'HEMAT200', 'nilai' => 200000]);
        $p = tagihanVoucher();

        pasangVoucherUji($this, $p, 'HEMAT100')->assertOk();
        pasangVoucherUji($this, $p, 'HEMAT200')->assertOk()->assertJsonPath('data.jumlah', '800000.00');
        expect(VoucherPemakaian::where('aktif', true)->count())->toBe(1)
            ->and(VoucherPemakaian::whereNull('aktif')->count())->toBe(1);

        $this->deleteJson("/api/checkout/{$p->kode_pendaftaran}/voucher")
            ->assertOk()
            ->assertJsonPath('data.jumlah', '1000000.00')
            ->assertJsonPath('data.voucher', null);
        expect($p->pembayaran->fresh()->voucher_id)->toBeNull();
    });

    it('terkunci setelah sesi DOKU dibuat atau saat bukti transfer diverifikasi', function () {
        voucherUji();
        $p = tagihanVoucher();
        $p->pembayaran->update(['gateway_payload' => ['response' => ['payment' => ['url' => 'https://doku.test/x']]]]);

        pasangVoucherUji($this, $p, 'HEMAT100')->assertStatus(422)
            ->assertJsonPath('errors.kode.0', fn ($m) => str_contains($m, 'sesi pembayaran online'));

        $q = tagihanVoucher();
        $rek = RekeningPembayaran::create(['nama_bank' => 'BCA', 'nomor_rekening' => '1', 'atas_nama' => 'LSP']);
        BuktiPembayaran::create([
            'pembayaran_id' => $q->pembayaran->id, 'rekening_pembayaran_id' => $rek->id, 'rekening_tujuan' => [],
            'nama_pengirim' => 'A', 'bank_pengirim' => 'BCA', 'tanggal_transfer' => now(), 'jumlah_transfer' => 1000000,
            'path_file' => 'x.jpg', 'nama_file_asli' => 'x.jpg', 'mime_type' => 'image/jpeg', 'ukuran_file' => 1,
            'status' => BuktiPembayaran::MENUNGGU,
        ]);
        pasangVoucherUji($this, $q, 'HEMAT100')->assertStatus(422)
            ->assertJsonPath('errors.kode.0', fn ($m) => str_contains($m, 'bukti transfer'));
    });

    it('potongan 100% langsung lunas dengan metode VOUCHER', function () {
        voucherUji(['kode' => 'GRATIS', 'tipe' => Voucher::PERSENTASE, 'nilai' => 100]);
        $p = tagihanVoucher();

        pasangVoucherUji($this, $p, 'GRATIS')->assertOk()->assertJsonPath('data.status', 'paid');

        $bayar = $p->pembayaran->fresh();
        expect($bayar->status)->toBe('paid')
            ->and((int) $bayar->jumlah)->toBe(0)
            ->and($bayar->metode_pembayaran)->toBe('VOUCHER')
            ->and($p->fresh()->status)->toBe('paid');
    });

    it('asesi lain tidak bisa memasang voucher pada tagihan orang lain', function () {
        voucherUji();
        $p = tagihanVoucher();
        $lain = Asesi::factory()->create();

        $this->withToken($lain->createToken('auth_token')->plainTextToken)
            ->postJson("/api/checkout/{$p->kode_pendaftaran}/voucher", ['kode' => 'HEMAT100'])
            ->assertForbidden();
    });
});

it('webhook DOKU dengan nominal kurang dari tagihan tidak dinyatakan lunas', function () {
    $p = tagihanVoucher();

    webhookDokuVoucher($this, [
        'order' => ['invoice_number' => $p->pembayaran->kode_transaksi, 'amount' => 900000],
        'transaction' => ['status' => 'SUCCESS'],
    ])->assertOk();
    expect($p->pembayaran->fresh()->status)->toBe('pending');

    webhookDokuVoucher($this, [
        'order' => ['invoice_number' => $p->pembayaran->kode_transaksi, 'amount' => 1000000],
        'transaction' => ['status' => 'SUCCESS'],
    ])->assertOk();
    expect($p->pembayaran->fresh()->status)->toBe('paid');
});

describe('CMS voucher', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
    });

    it('membuat voucher (kode dinormalisasi, waktu WIB -> UTC) dan menampilkan riwayat', function () {
        $this->actingAs($this->admin)->post(route('vouchers.store'), [
            'kode' => 'promo 17', 'tipe' => 'persentase', 'nilai' => '17.5', 'maks_potongan' => 300000,
            'mulai_at' => '2026-11-10T00:00', 'berakhir_at' => '2026-11-17T23:59', 'kuota' => 50, 'aktif' => '1',
        ])->assertRedirect();

        $v = Voucher::sole();
        expect($v->kode)->toBe('PROMO17')
            ->and($v->mulai_at->toIso8601String())->toBe('2026-11-09T17:00:00+00:00')
            ->and($v->aktif)->toBeTrue();

        $this->actingAs($this->admin)->get(route('vouchers.index'))->assertOk()->assertSee('PROMO17');
        $this->actingAs($this->admin)->get(route('vouchers.show', $v))->assertOk()->assertSee('Voucher belum pernah dipakai');
        $this->actingAs($this->admin)->get(route('vouchers.edit', $v))->assertOk();
    });

    it('validasi: persentase > 100, kode duplikat (termasuk yang dihapus), tanggal terbalik', function () {
        voucherUji()->delete();

        $this->actingAs($this->admin)->post(route('vouchers.store'), [
            'kode' => 'hemat100', 'tipe' => 'persentase', 'nilai' => 120,
            'mulai_at' => '2026-11-10T00:00', 'berakhir_at' => '2026-11-01T00:00',
        ])->assertSessionHasErrors(['kode', 'nilai', 'berakhir_at']);
    });

    it('kode terkunci setelah dipakai & kuota tidak boleh di bawah pemakaian', function () {
        $v = voucherUji(['kuota' => 5]);
        pasangVoucherUji($this, tagihanVoucher(), 'HEMAT100')->assertOk();
        pasangVoucherUji($this, tagihanVoucher(), 'HEMAT100')->assertOk();

        $data = ['kode' => 'GANTI', 'tipe' => 'nominal', 'nilai' => 50000, 'mulai_at' => '2026-11-01T00:00', 'berakhir_at' => '2026-12-01T00:00', 'aktif' => '1'];
        $this->actingAs($this->admin)->put(route('vouchers.update', $v), [...$data, 'kuota' => 1])->assertSessionHas('error');
        $this->actingAs($this->admin)->put(route('vouchers.update', $v), [...$data, 'kuota' => 10])->assertRedirect();

        expect($v->fresh()->kode)->toBe('HEMAT100')->and($v->fresh()->kuota)->toBe(10);
    });
});

describe('reset sesi pembayaran DOKU (CMS)', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
    });

    it('admin reset: kode transaksi baru, voucher bisa dipasang, link lama tetap tercatat', function () {
        voucherUji();
        $p = tagihanVoucher();
        $kodeLama = $p->pembayaran->kode_transaksi;
        $p->pembayaran->update(['gateway_payload' => ['response' => ['payment' => ['url' => 'https://doku.test/lama']]]]);

        $this->actingAs($this->admin)->get(route('pendaftaran.show', $p->kode_pendaftaran))
            ->assertOk()->assertSee('Reset Sesi Pembayaran');

        $this->actingAs($this->admin)
            ->post(route('pendaftaran.reset-sesi-pembayaran', $p->kode_pendaftaran))
            ->assertSessionHas('success');

        $bayar = $p->pembayaran->fresh();
        expect($bayar->kode_transaksi)->not->toBe($kodeLama)
            ->and($bayar->gateway_payload)->toBeNull()
            ->and($bayar->sesi_doku_lama[0]['kode_transaksi'])->toBe($kodeLama)
            ->and($bayar->sesi_doku_lama[0]['direset_oleh'])->toBe($this->admin->id);

        pasangVoucherUji($this, $p, 'HEMAT100')->assertOk()->assertJsonPath('data.jumlah', '900000.00');

        // Expired dari sesi lama diabaikan (tagihan baru tetap pending).
        webhookDokuVoucher($this, ['order' => ['invoice_number' => $kodeLama, 'status' => 'ORDER_EXPIRED']])->assertOk();
        expect($bayar->fresh()->status)->toBe('pending');

        // Asesi ternyata membayar lewat link lama (nominal lama 1.000.000 >= tagihan 900.000): tetap lunas.
        webhookDokuVoucher($this, [
            'order' => ['invoice_number' => $kodeLama, 'amount' => 1000000],
            'transaction' => ['status' => 'SUCCESS'],
        ])->assertOk();
        expect($bayar->fresh()->status)->toBe('paid')->and($p->fresh()->status)->toBe('paid');
    });

    it('ditolak bila belum ada sesi DOKU atau tagihan sudah lunas', function () {
        $p = tagihanVoucher();

        $this->actingAs($this->admin)
            ->post(route('pendaftaran.reset-sesi-pembayaran', $p->kode_pendaftaran))
            ->assertSessionHas('error', 'Belum ada sesi pembayaran DOKU yang perlu direset.');

        $p->pembayaran->update(['status' => 'paid', 'gateway_payload' => ['response' => ['payment' => ['url' => 'https://doku.test/x']]]]);
        $this->actingAs($this->admin)
            ->post(route('pendaftaran.reset-sesi-pembayaran', $p->kode_pendaftaran))
            ->assertSessionHas('error', 'Tagihan tidak sedang menunggu pembayaran.');
    });
});
