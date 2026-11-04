<?php

use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\JenisDokumenMuk;
use App\Models\Pembayaran;
use App\Models\PendaftaranSertifikasi;
use App\Models\PenilaianMuk;
use App\Notifications\JadwalAsesmenDitentukanNotifikasi;
use App\Notifications\Muk\DokumenDitinjauNotifikasi;
use App\Notifications\Muk\DokumenPersetujuanNotifikasi;
use App\Notifications\PengingatAsesmenNotifikasi;
use App\Notifications\StatusPendaftaranNotifikasi;
use App\Services\Muk\NotifikasiMuk;
use App\Services\Muk\SinkronStatusAsesmen;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\MukFixture;

beforeEach(function () {
    Notification::fake();
    Cache::flush();
});

/** Pendaftaran milik asesi baru dengan status tertentu (skema dari fixture MUK). */
function pendaftaranUji(string $status): PendaftaranSertifikasi
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

describe('notifikasi status pendaftaran (observer riwayat)', function () {
    it('diajukan, pembayaran berhasil & kedaluwarsa dikirim ke asesi; status lain/sama tidak', function () {
        $p = pendaftaranUji('submitted');
        $asesi = $p->asesi;

        $p->riwayatStatus()->create(['status_dari' => null, 'status_ke' => 'submitted', 'keterangan' => 'Diajukan.']);
        $p->riwayatStatus()->create(['status_dari' => 'submitted', 'status_ke' => 'awaiting_payment', 'keterangan' => 'Disetujui.']);
        $p->riwayatStatus()->create(['status_dari' => 'awaiting_payment', 'status_ke' => 'paid', 'keterangan' => 'Lunas.']);
        $p->riwayatStatus()->create(['status_dari' => 'paid', 'status_ke' => 'paid', 'keterangan' => 'Duplikat.']);

        Notification::assertSentToTimes($asesi, StatusPendaftaranNotifikasi::class, 2);
        Notification::assertSentTo($asesi, StatusPendaftaranNotifikasi::class, function ($n, $channels) use ($asesi) {
            $data = $n->toArray($asesi);

            return $n->status === 'paid'
                && $channels === ['mail', 'database']
                && $data['judul'] === 'Pembayaran berhasil'
                && str_contains($data['pesan'], $n->pendaftaran->skemaSertifikasi->title['id'])
                && $data['url'] === '/profile';
        });
    });

    it('checkout dengan batas bayar lewat: status kedaluwarsa tercatat di riwayat & asesi dikabari', function () {
        $p = pendaftaranUji('awaiting_payment');
        Pembayaran::create([
            'kode_transaksi' => 'INV-'.Str::random(8),
            'pendaftaran_sertifikasi_id' => $p->id,
            'jumlah' => 500000,
            'status' => 'pending',
            'expired_at' => now()->subHour(),
        ]);

        $this->withToken($p->asesi->createToken('auth_token')->plainTextToken)
            ->getJson("/api/checkout/{$p->kode_pendaftaran}")
            ->assertStatus(410);

        expect($p->fresh()->status)->toBe('expired')
            ->and($p->riwayatStatus()->where('status_ke', 'expired')->exists())->toBeTrue();
        Notification::assertSentTo($p->asesi, StatusPendaftaranNotifikasi::class, fn ($n) => $n->status === 'expired'
            && $n->toArray($p->asesi)['judul'] === 'Batas waktu pembayaran habis');
    });

    it('hasil asesmen dari MUK (asesmen_selesai) dikirim dengan hasil kompeten', function () {
        $penilaian = MukFixture::penilaian();
        $penilaian->update(['status' => PenilaianMuk::STATUS_SELESAI_KOMPETEN, 'selesai_at' => now()]);

        app(SinkronStatusAsesmen::class)->selesai($penilaian->fresh());

        $asesi = $penilaian->pendaftaran->asesi;
        Notification::assertSentTo($asesi, StatusPendaftaranNotifikasi::class, fn ($n) => $n->status === 'asesmen_selesai'
            && str_contains($n->toArray($asesi)['pesan'], 'KOMPETEN')
            && ! str_contains($n->toArray($asesi)['pesan'], 'BELUM KOMPETEN'));
    });
});

describe('channel database untuk asesi', function () {
    it('jadwal & dokumen MUK tersimpan untuk asesi (bukan asesor), tautan langsung ke MUK', function () {
        $penilaian = MukFixture::penilaian();
        $asesi = $penilaian->pendaftaran->asesi;
        $asesor = Asesor::factory()->create();
        $jadwal = $penilaian->jadwalAsesmen;

        $n = new JadwalAsesmenDitentukanNotifikasi($jadwal);
        expect($n->via($asesi))->toBe(['mail', 'database'])
            ->and($n->via($asesor))->toBe(['mail'])
            ->and($n->toArray($asesi))->toMatchArray([
                'kategori' => 'jadwal',
                'url' => config('muk.frontend_url')."/dashboard/penilaian/{$penilaian->id}",
                'kode_pendaftaran' => $penilaian->pendaftaran->kode_pendaftaran,
            ])
            ->and($n->toArray($asesi)['pesan'])->toContain('1 November 2026');

        $dokumen = MukFixture::dokumen($penilaian, JenisDokumenMuk::APL_01);
        $minta = new DokumenPersetujuanNotifikasi($dokumen, DokumenPersetujuanNotifikasi::MINTA);
        expect($minta->via($asesi))->toBe(['mail', 'database'])
            ->and($minta->via($asesor))->toBe(['mail'])
            ->and($minta->toArray($asesi)['url'])->toBe(config('muk.frontend_url')."/dashboard/penilaian/{$penilaian->id}/dokumen/apl-01")
            ->and($minta->toArray($asesi)['judul'])->toBe('FR.APL.01 menunggu persetujuan Anda');

        $dokumen->update(['catatan_admin' => 'Foto KTP buram']);
        $ditinjau = new DokumenDitinjauNotifikasi($dokumen->fresh(), false, 'admin LSP');
        expect($ditinjau->via($asesi))->toBe(['mail', 'database'])
            ->and($ditinjau->toArray($asesi)['pesan'])->toContain('Foto KTP buram');
    });

    it('notifikasi tersimpan muncul di API profil dengan format standar', function () {
        $penilaian = MukFixture::penilaian();
        $asesi = $penilaian->pendaftaran->asesi;
        // Notification::fake aktif: simpan baris database seperti channel database (data = toArray()).
        $n = new DokumenPersetujuanNotifikasi(MukFixture::dokumen($penilaian, JenisDokumenMuk::APL_02), DokumenPersetujuanNotifikasi::MINTA);
        $asesi->notifications()->create(['id' => (string) Str::uuid(), 'type' => $n::class, 'data' => $n->toArray($asesi)]);

        $this->withToken($asesi->createToken('auth_token')->plainTextToken)->getJson('/api/notifikasi')
            ->assertOk()
            ->assertJsonPath('data.0.kategori', 'asesmen')
            ->assertJsonPath('data.0.judul', 'FR.APL.02 menunggu persetujuan Anda')
            ->assertJsonPath('data.0.url', config('muk.frontend_url')."/dashboard/penilaian/{$penilaian->id}/dokumen/apl-02");
    });
});

describe('asesmen:kirim-pengingat', function () {
    it('H-3 & H-1 dikirim sekali per jadwal; jadwal lain/dibatalkan tidak', function () {
        $hariIni = now(config('muk.zona_waktu'));
        $h3 = MukFixture::penilaian(tanggal: $hariIni->copy()->addDays(3)->toDateString());
        $h1 = MukFixture::penilaian(tanggal: $hariIni->copy()->addDay()->toDateString());
        $h2 = MukFixture::penilaian(tanggal: $hariIni->copy()->addDays(2)->toDateString());
        $batal = MukFixture::penilaian(tanggal: $hariIni->copy()->addDays(3)->toDateString());
        $batal->jadwalAsesmen->update(['status' => 'dibatalkan']);

        $this->artisan('asesmen:kirim-pengingat')->expectsOutputToContain('2 pengingat')->assertSuccessful();
        $this->artisan('asesmen:kirim-pengingat')->expectsOutputToContain('0 pengingat')->assertSuccessful();

        $asesiH3 = $h3->pendaftaran->asesi;
        Notification::assertSentToTimes($asesiH3, PengingatAsesmenNotifikasi::class, 1);
        Notification::assertSentTo($asesiH3, PengingatAsesmenNotifikasi::class, fn ($n) => $n->hari === 3
            && str_contains($n->toArray($asesiH3)['pesan'], '3 hari lagi'));
        Notification::assertSentTo($h1->pendaftaran->asesi, PengingatAsesmenNotifikasi::class, fn ($n) => $n->hari === 1
            && str_contains($n->toArray($h1->pendaftaran->asesi)['pesan'], 'besok'));
        Notification::assertNotSentTo($h2->pendaftaran->asesi, PengingatAsesmenNotifikasi::class);
        Notification::assertNotSentTo($batal->pendaftaran->asesi, PengingatAsesmenNotifikasi::class);
    });
});

it('email "sudah disetujui" ke asesor nonaktif secara default, aktif lewat config', function () {
    $penilaian = MukFixture::penilaian();
    $asesor = $penilaian->jadwalAsesmen->asesor;
    $dokumen = MukFixture::dokumen($penilaian, JenisDokumenMuk::APL_02);

    app(NotifikasiMuk::class)->setelahDisetujui($dokumen, 'asesi');
    Notification::assertNotSentTo($asesor, DokumenPersetujuanNotifikasi::class);

    config(['muk.notifikasi.asesor_setelah_disetujui' => true]);
    app(NotifikasiMuk::class)->setelahDisetujui($dokumen, 'asesi');
    Notification::assertSentTo($asesor, DokumenPersetujuanNotifikasi::class);
});
