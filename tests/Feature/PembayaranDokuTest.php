<?php

use App\Models\Asesi;
use App\Models\Pembayaran;
use App\Models\PendaftaranSertifikasi;
use App\Notifications\StatusPendaftaranNotifikasi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\MukFixture;

beforeEach(function () {
    Notification::fake();
    config(['services.doku.client_id' => 'BRN-TEST', 'services.doku.secret_key' => 'SK-TEST', 'services.doku.is_production' => false]);
});

/** Pendaftaran menunggu pembayaran + tagihan pending. */
function tagihanMenunggu(): PendaftaranSertifikasi
{
    $p = PendaftaranSertifikasi::create([
        'kode_pendaftaran' => (string) Str::uuid(),
        'asesi_id' => Asesi::factory()->create()->id,
        'skema_sertifikasi_id' => MukFixture::skema()->id,
        'status' => 'awaiting_payment',
        'harga_snapshot' => 500000,
        'submitted_at' => now(),
    ]);
    Pembayaran::create([
        'kode_transaksi' => 'INV-'.Str::random(10),
        'pendaftaran_sertifikasi_id' => $p->id,
        'jumlah' => 500000,
        'status' => 'pending',
        'expired_at' => now()->addDay(),
    ]);

    return $p->load('pembayaran');
}

/** Kirim webhook DOKU bertanda tangan valid (sesuai DokuWebhookController::signatureValid). */
function kirimWebhookDoku($test, array $payload)
{
    $body = json_encode($payload);
    $header = ['Client-Id' => 'BRN-TEST', 'Request-Id' => (string) Str::uuid(), 'Request-Timestamp' => now()->utc()->format('Y-m-d\TH:i:s\Z')];
    $komponen = implode("\n", [
        'Client-Id:'.$header['Client-Id'],
        'Request-Id:'.$header['Request-Id'],
        'Request-Timestamp:'.$header['Request-Timestamp'],
        'Request-Target:/api/doku/callback',
        'Digest:'.base64_encode(hash('sha256', $body, true)),
    ]);
    $header['Signature'] = 'HMACSHA256='.base64_encode(hash_hmac('sha256', $komponen, 'SK-TEST', true));

    return $test->call('POST', '/api/doku/callback', [], [], [], collect($header)
        ->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])
        ->merge(['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'])->all(), $body);
}

describe('POST /api/checkout/{kode}/pay', function () {
    it('menyimpan payment_url DOKU dan memakainya ulang pada klik berikutnya', function () {
        $p = tagihanMenunggu();
        Http::fake(['api-sandbox.doku.com/*' => Http::response(['response' => ['payment' => ['url' => 'https://sandbox.doku.com/bayar/abc', 'token_id' => 'tok-1']]])]);
        $token = $p->asesi->createToken('auth_token')->plainTextToken;

        $this->withToken($token)->postJson("/api/checkout/{$p->kode_pendaftaran}/pay")
            ->assertOk()->assertJsonPath('payment_url', 'https://sandbox.doku.com/bayar/abc');
        expect($p->pembayaran->fresh()->external_transaction_id)->toBe('tok-1');

        $this->withToken($token)->postJson("/api/checkout/{$p->kode_pendaftaran}/pay")
            ->assertOk()->assertJsonPath('payment_url', 'https://sandbox.doku.com/bayar/abc');
        Http::assertSentCount(1); // dipakai ulang, tidak membuat sesi DOKU baru
    });

    it('mengirim rincian line_items: skema harga asli + potongan voucher, totalnya sama dengan amount', function () {
        $p = tagihanMenunggu();
        $skema = 'Sertifikasi '.$p->skemaSertifikasi->title['id'];
        Http::fake(['api-sandbox.doku.com/*' => Http::response(['response' => ['payment' => ['url' => 'https://sandbox.doku.com/bayar/x', 'token_id' => 't']]])]);
        $bayar = function () use ($p) {
            $p->pembayaran->fresh()->update(['gateway_payload' => null]);
            $this->withToken($p->asesi->createToken('auth_token')->plainTextToken)->postJson("/api/checkout/{$p->kode_pendaftaran}/pay")->assertOk();
            app('auth')->forgetGuards();
        };
        $terkirim = fn () => json_decode(Http::recorded()->last()[0]->body(), true)['order'];

        // Tanpa voucher: satu baris skema seharga tagihan.
        $bayar();
        expect($terkirim()['line_items'])->toBe([['name' => $skema, 'price' => 500000, 'quantity' => 1]]);

        // Dengan voucher: harga asli + baris potongan (minus); jumlah = order.amount.
        $p->pembayaran->update(['jumlah_awal' => 500000, 'potongan' => 150000, 'jumlah' => 350000, 'voucher_kode' => 'HEMAT30']);
        $bayar();
        $o = $terkirim();
        expect($o['line_items'])->toBe([
            ['name' => $skema, 'price' => 500000, 'quantity' => 1],
            ['name' => 'Potongan voucher HEMAT30', 'price' => -150000, 'quantity' => 1],
        ])->and(collect($o['line_items'])->sum(fn ($i) => $i['price'] * $i['quantity']))->toBe($o['amount']);

        // Data lama tidak konsisten: satu baris seharga tagihan (DOKU menolak bila total tidak sama).
        $p->pembayaran->update(['potongan' => 100000]);
        $bayar();
        expect($terkirim()['line_items'])->toBe([['name' => $skema, 'price' => 350000, 'quantity' => 1]]);
    });

    it('DOKU tidak dapat dihubungi -> 503 dengan pesan jelas', function () {
        $p = tagihanMenunggu();
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Timeout was reached'));

        $this->withToken($p->asesi->createToken('auth_token')->plainTextToken)
            ->postJson("/api/checkout/{$p->kode_pendaftaran}/pay")
            ->assertStatus(503)
            ->assertJsonPath('message', 'Layanan pembayaran sedang tidak dapat dihubungi. Silakan coba beberapa saat lagi.');
        expect($p->fresh()->status)->toBe('awaiting_payment');
    });
});

describe('webhook DOKU', function () {
    it('SUCCESS: lunas, payload & metode tersimpan, riwayat & notifikasi; duplikat diabaikan', function () {
        $p = tagihanMenunggu();
        $payload = [
            'channel' => ['id' => 'VIRTUAL_ACCOUNT_BCA'],
            'transaction' => ['status' => 'SUCCESS', 'original_request_id' => 'req-123'],
            'order' => ['invoice_number' => $p->pembayaran->kode_transaksi, 'amount' => 500000],
        ];

        kirimWebhookDoku($this, $payload)->assertOk();
        kirimWebhookDoku($this, $payload)->assertOk()->assertJsonPath('message', 'Sudah diproses sebelumnya');

        $bayar = $p->pembayaran->fresh();
        expect($bayar->status)->toBe('paid')
            ->and($bayar->metode_pembayaran)->toBe('VIRTUAL_ACCOUNT_BCA')
            ->and($bayar->external_transaction_id)->toBe('req-123')
            ->and($bayar->gateway_payload['channel']['id'])->toBe('VIRTUAL_ACCOUNT_BCA')
            ->and($p->fresh()->status)->toBe('paid')
            ->and($p->riwayatStatus()->where('status_ke', 'paid')->count())->toBe(1);
        Notification::assertSentToTimes($p->asesi, StatusPendaftaranNotifikasi::class, 1);
    });

    it('signature salah ditolak', function () {
        $p = tagihanMenunggu();
        $this->postJson('/api/doku/callback', ['order' => ['invoice_number' => $p->pembayaran->kode_transaksi]], ['Signature' => 'HMACSHA256=salah'])
            ->assertUnauthorized();
        expect($p->fresh()->status)->toBe('awaiting_payment');
    });
});

it('pembayaran:simulasi-lunas menandai lunas seperti webhook (hanya lokal/testing)', function () {
    $p = tagihanMenunggu();

    $this->artisan('pembayaran:simulasi-lunas', ['kode' => $p->kode_pendaftaran])
        ->expectsOutputToContain('ditandai LUNAS')->assertSuccessful();
    $this->artisan('pembayaran:simulasi-lunas', ['kode' => $p->kode_pendaftaran])
        ->expectsOutputToContain('sudah lunas sebelumnya')->assertSuccessful();
    $this->artisan('pembayaran:simulasi-lunas', ['kode' => 'tidak-ada'])->assertFailed();

    expect($p->fresh()->status)->toBe('paid')
        ->and($p->pembayaran->fresh()->metode_pembayaran)->toBe('SIMULASI_LOKAL');
    Notification::assertSentTo($p->asesi, StatusPendaftaranNotifikasi::class, fn ($n) => $n->status === 'paid');

    // Di luar lokal/testing ditolak.
    app()->detectEnvironment(fn () => 'production');
    $this->artisan('pembayaran:simulasi-lunas', ['kode' => $p->kode_pendaftaran])->assertFailed();
});
