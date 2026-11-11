<?php

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\PenerbitanSertifikasi;
use App\Models\PenilaianMuk;
use App\Models\RiwayatDokumenPenilaian;
use App\Models\User;
use App\Notifications\Muk\DokumenDikoreksiNotifikasi;
use App\Services\Sertifikasi\PenerbitanSertifikasiService;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Support\MukFixture;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super-admin');
    Notification::fake();
});

/** Token MUK super admin untuk satu penilaian (lewat alur "Buka di MUK" CMS). */
function korToken($test, PenilaianMuk $p): string
{
    $lokasi = $test->actingAs($test->superAdmin)->post("/muk/{$p->id}/buka")->headers->get('Location');
    app('auth')->forgetGuards();

    return $test->postJson('/api/muk/auth/admin/tukar', ['kode' => substr($lokasi, strpos($lokasi, '#kode=') + 6)])
        ->assertOk()->json('data.token');
}

function korDokumen(PenilaianMuk $p, string $kode, array $data, string $status = DokumenPenilaian::STATUS_SELESAI): DokumenPenilaian
{
    return DokumenPenilaian::updateOrCreate(
        ['penilaian_muk_id' => $p->id, 'jenis_dokumen_muk_id' => JenisDokumenMuk::where('kode', $kode)->value('id')],
        [
            'status' => $status,
            'submitted_at' => now(),
            'data' => $data,
            'path_tanda_tangan_asesor' => 'muk/ttd/asesor.png',
            'tanggal_tanda_tangan_asesor' => '2026-11-01',
        ],
    );
}

it('super admin mengoreksi dokumen terkirim: tanda tangan tetap, riwayat sebelum/sesudah, asesor diberi tahu', function () {
    $p = MukFixture::penilaian();
    $d = korDokumen($p, JenisDokumenMuk::AK_05, ['tuk' => 'sewaktu', 'tanggal' => '2026-11-01', 'aspek' => 'Lama']);
    $token = korToken($this, $p);
    $url = "/api/muk/penilaian/{$p->id}/dokumen/ak-05";

    $this->withToken($token)->getJson($url)->assertOk()
        ->assertJsonPath('data.dokumen.koreksi.bisa', true)
        ->assertJsonPath('data.dokumen.dapat_diubah', false);

    // Alasan wajib; isian tetap divalidasi aturan dokumen; tidak boleh jadi tidak lengkap.
    $this->withToken($token)->patchJson("{$url}/koreksi", ['isian' => ['aspek' => 'Baru']])->assertJsonValidationErrors('alasan');
    $this->withToken($token)->patchJson("{$url}/koreksi", ['isian' => ['tuk' => 'ngawur'], 'alasan' => 'Salah ketik TUK'])->assertJsonValidationErrors('isian.tuk');
    $this->withToken($token)->patchJson("{$url}/koreksi", ['isian' => ['tanggal' => null], 'alasan' => 'Kosongkan tanggal'])->assertJsonValidationErrors('isian.tanggal');
    expect($d->fresh()->data['tanggal'])->toBe('2026-11-01');

    $this->withToken($token)->patchJson("{$url}/koreksi", ['isian' => ['aspek' => 'Baru'], 'alasan' => '  Salah ketik aspek  '])
        ->assertOk()
        ->assertJsonPath('meta.jumlah_perubahan', 1)
        ->assertJsonPath('data.isian.aspek', 'Baru')
        ->assertJsonPath('data.dokumen.status', DokumenPenilaian::STATUS_SELESAI)
        ->assertJsonPath('data.dokumen.tanda_tangan.asesor.ada', true);

    $r = RiwayatDokumenPenilaian::where('dokumen_penilaian_id', $d->id)->where('aksi', 'dikoreksi_admin')->sole();
    expect($r->catatan)->toBe('Salah ketik aspek')
        ->and($r->pelaku_id)->toBe($this->superAdmin->id)
        ->and($r->meta['perubahan'])->toBe([['field' => 'aspek', 'sebelum' => 'Lama', 'sesudah' => 'Baru']]);
    Notification::assertSentTo($p->jadwalAsesmen->asesor, DokumenDikoreksiNotifikasi::class);

    // Riwayat koreksi tampil di detail asesmen CMS.
    $this->actingAs($this->superAdmin)->get(route('asesmen.show', $p->jadwalAsesmen))
        ->assertOk()
        ->assertSee('Riwayat Koreksi Dokumen')
        ->assertSee('Salah ketik aspek')
        ->assertSee('1 isian berubah');
    app('auth')->forgetGuards();

    // Tanpa perubahan: tidak ada riwayat/notifikasi baru.
    $this->withToken($token)->patchJson("{$url}/koreksi", ['isian' => ['aspek' => 'Baru'], 'alasan' => 'Tidak ada yang berubah'])
        ->assertOk()->assertJsonPath('meta.jumlah_perubahan', 0);
    expect(RiwayatDokumenPenilaian::where('dokumen_penilaian_id', $d->id)->where('aksi', 'dikoreksi_admin')->count())->toBe(1);
    Notification::assertSentToTimes($p->jadwalAsesmen->asesor, DokumenDikoreksiNotifikasi::class, 1);
});

it('koreksi ditolak untuk draft, peran lain, penilaian lain, dan setelah sertifikat terbit', function () {
    $p = MukFixture::penilaian();
    $lain = MukFixture::penilaian();
    korDokumen($p, JenisDokumenMuk::AK_05, ['tuk' => 'sewaktu', 'tanggal' => '2026-11-01'], DokumenPenilaian::STATUS_DRAFT);
    korDokumen($lain, JenisDokumenMuk::AK_05, ['tuk' => 'sewaktu', 'tanggal' => '2026-11-01']);
    $token = korToken($this, $p);
    $body = ['isian' => ['aspek' => 'X'], 'alasan' => 'Perbaikan data asesmen'];

    $this->withToken($token)->patchJson("/api/muk/penilaian/{$p->id}/dokumen/ak-05/koreksi", $body)->assertForbidden();
    $this->withToken($token)->patchJson("/api/muk/penilaian/{$lain->id}/dokumen/ak-05/koreksi", $body)->assertNotFound();
    $asesor = MukFixture::token($lain->jadwalAsesmen->asesor);
    app('auth')->forgetGuards();
    $this->withToken($asesor)->patchJson("/api/muk/penilaian/{$lain->id}/dokumen/ak-05/koreksi", $body)->assertForbidden();
    app('auth')->forgetGuards();

    // Sertifikat terbit -> terkunci.
    $d = MukFixture::dokumen($p, JenisDokumenMuk::AK_05);
    $d->update(['status' => DokumenPenilaian::STATUS_SELESAI]);
    $p->update(['status' => PenilaianMuk::STATUS_SELESAI_KOMPETEN]);
    app(PenerbitanSertifikasiService::class)->buatUntuk($p)->update(['status' => PenerbitanSertifikasi::SELESAI]);
    $this->withToken($token)->getJson("/api/muk/penilaian/{$p->id}/dokumen/ak-05")
        ->assertOk()->assertJsonPath('data.dokumen.koreksi.bisa', false);
    $this->withToken($token)->patchJson("/api/muk/penilaian/{$p->id}/dokumen/ak-05/koreksi", $body)->assertForbidden();
    expect($d->fresh()->data['aspek'] ?? null)->toBeNull();
    Notification::assertNothingSent();
});

it('koreksi rekomendasi FR.AK.02 setelah penilaian selesai ikut mengubah status penilaian & hasil penerbitan', function () {
    $p = MukFixture::penilaian();
    $ak02 = korDokumen($p, JenisDokumenMuk::AK_02, []);
    $token = korToken($this, $p);
    $url = "/api/muk/penilaian/{$p->id}/dokumen/ak-02";
    $bukti = collect($this->withToken($token)->getJson($url)->assertOk()->json('data.isian.bukti'))->map(fn () => ['observasi'])->all();
    $ak02->update(['data' => [
        'tuk' => 'sewaktu', 'tanggal_mulai' => '2026-11-01', 'tanggal_selesai' => '2026-11-01',
        'bukti' => $bukti, 'rekomendasi' => 'kompeten',
    ]]);
    $p->update(['status' => PenilaianMuk::STATUS_SELESAI_KOMPETEN]);
    $penerbitan = app(PenerbitanSertifikasiService::class)->buatUntuk($p);

    // BK wajib tindak lanjut (aturan Kirim tetap berlaku).
    $this->withToken($token)->patchJson("{$url}/koreksi", ['isian' => ['rekomendasi' => 'belum_kompeten'], 'alasan' => 'Salah pilih rekomendasi'])
        ->assertJsonValidationErrors('isian.tindak_lanjut');
    expect($p->fresh()->status)->toBe(PenilaianMuk::STATUS_SELESAI_KOMPETEN);

    $this->withToken($token)->patchJson("{$url}/koreksi", [
        'isian' => ['rekomendasi' => 'belum_kompeten', 'tindak_lanjut' => 'Ulang unit 1'],
        'alasan' => 'Salah pilih rekomendasi',
    ])->assertOk();
    expect($p->fresh()->status)->toBe(PenilaianMuk::STATUS_SELESAI_BELUM_KOMPETEN)
        ->and($penerbitan->fresh()->hasil)->toBe(PenerbitanSertifikasi::BELUM_KOMPETEN)
        ->and($penerbitan->riwayat()->where('aksi', 'hasil_dikoreksi')->count())->toBe(1);
});
