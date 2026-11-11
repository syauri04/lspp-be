<?php

use App\Models\DokumenPenilaian;
use App\Models\JadwalAsesmen;
use App\Models\JenisDokumenMuk;
use App\Models\PendaftaranSertifikasi;
use App\Models\PenerbitanSertifikasi;
use App\Models\PenilaianMuk;
use App\Models\Sertifikat;
use App\Models\User;
use App\Notifications\PengingatAsesmenNotifikasi;
use App\Notifications\SertifikatAkanKedaluwarsaNotifikasi;
use App\Services\Muk\Dokumen\Ak02Handler;
use App\Services\Muk\DokumenWorkflow;
use App\Services\Pembayaran\KonfirmasiPembayaran;
use App\Support\Cms\NotifikasiAdmin;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

// Hapus token Sanctum yang sudah kedaluwarsa > 24 jam (token MUK berumur 8 jam).
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Pengingat asesmen ke asesi H-3 & H-1 (email + notifikasi website). Aman dijalankan berulang:
// tiap jadwal & hari hanya dikirim sekali (kunci cache + cek tabel notifications).
Schedule::command('asesmen:kirim-pengingat')->dailyAt('07:00')->timezone('Asia/Jakarta');

Artisan::command('asesmen:kirim-pengingat', function () {
    $hariIni = now(config('muk.zona_waktu'))->startOfDay();
    $jumlah = 0;

    foreach ([3, 1] as $hari) {
        JadwalAsesmen::query()
            ->whereDate('tanggal_asesmen', $hariIni->copy()->addDays($hari)->toDateString())
            ->where('status', 'terjadwal')
            ->whereHas('pendaftaran', fn ($q) => $q->where('status', 'dijadwalkan'))
            ->with('pendaftaran.asesi')
            ->each(function (JadwalAsesmen $jadwal) use ($hari, &$jumlah) {
                $asesi = $jadwal->pendaftaran?->asesi;
                if (! $asesi) {
                    return;
                }
                $sudah = $asesi->notifications()
                    ->where('type', PengingatAsesmenNotifikasi::class)
                    ->where('data->jadwal_id', $jadwal->id)
                    ->where('data->hari', $hari)
                    ->exists();
                if ($sudah || ! Cache::add("pengingat-asesmen:{$jadwal->id}:{$hari}", true, now()->addDays(5))) {
                    return;
                }
                $asesi->notify(new PengingatAsesmenNotifikasi($jadwal, $hari));
                $jumlah++;
            });
    }

    $this->info("{$jumlah} pengingat asesmen dikirim.");
})->purpose('Kirim pengingat asesmen H-3 & H-1 ke asesi');

// Pengingat e-sertifikat akan kedaluwarsa (H-1 bulan, config sertifikasi.pengingat_kedaluwarsa_bulan) ke asesi
// & admin. Tiap sertifikat sekali saja (kolom pengingat_kedaluwarsa_at diklaim atomik sebelum mengirim);
// hari yang terlewat (cron mati) tetap terkejar selama belum lewat tanggal kedaluwarsa.
Schedule::command('sertifikat:kirim-pengingat-kedaluwarsa')->dailyAt('07:00')->timezone('Asia/Jakarta')->withoutOverlapping();

Artisan::command('sertifikat:kirim-pengingat-kedaluwarsa', function () {
    $hariIni = now(config('muk.zona_waktu'))->startOfDay();
    $batas = $hariIni->copy()->addMonthsNoOverflow((int) config('sertifikasi.pengingat_kedaluwarsa_bulan', 1));
    $admin = User::whereHas('roles', fn ($q) => $q->whereIn('name', NotifikasiAdmin::PERAN))->get();
    $jumlah = 0;

    Sertifikat::query()
        ->where('status', Sertifikat::BERLAKU)
        ->whereNull('pengingat_kedaluwarsa_at')
        ->whereDate('tanggal_expired', '>=', $hariIni->toDateString())
        ->whereDate('tanggal_expired', '<=', $batas->toDateString())
        // Hanya sertifikat yang sudah diterbitkan ke asesi (proses penerbitan selesai).
        ->whereHas('penerbitan', fn ($q) => $q->where('status', PenerbitanSertifikasi::SELESAI))
        ->with('penerbitan.pendaftaran.asesi')
        ->chunkById(100, function ($daftar) use ($admin, &$jumlah) {
            foreach ($daftar as $sertifikat) {
                // Klaim atomik: proses lain yang berjalan bersamaan tidak akan mengirim dobel.
                $diklaim = Sertifikat::whereKey($sertifikat->id)->whereNull('pengingat_kedaluwarsa_at')
                    ->update(['pengingat_kedaluwarsa_at' => now()]);
                if (! $diklaim) {
                    continue;
                }
                $notifikasi = new SertifikatAkanKedaluwarsaNotifikasi($sertifikat);
                $sertifikat->penerbitan?->pendaftaran?->asesi?->notify($notifikasi);
                $admin->each(fn (User $u) => $u->notify($notifikasi));
                $jumlah++;
            }
        });

    $this->info("{$jumlah} pengingat e-sertifikat kedaluwarsa dikirim.");
})->purpose('Kirim pengingat e-sertifikat akan kedaluwarsa ke asesi & admin');

// KHUSUS LOKAL: tandai pembayaran lunas tanpa DOKU (webhook DOKU sandbox mengarah ke staging, jadi
// database lokal tidak pernah menerima konfirmasi). Alurnya sama dengan webhook: status, riwayat, notifikasi.
Artisan::command('pembayaran:simulasi-lunas {kode : kode_pendaftaran (lihat URL checkout)}', function (string $kode) {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('Perintah ini hanya untuk APP_ENV=local.');

        return 1;
    }

    $pendaftaran = PendaftaranSertifikasi::where('kode_pendaftaran', $kode)->with('pembayaran')->first();
    if (! $pendaftaran) {
        $this->error("Pendaftaran {$kode} tidak ditemukan.");

        return 1;
    }
    if (! $pendaftaran->pembayaran) {
        $this->error('Pendaftaran ini belum punya tagihan (belum disetujui admin).');

        return 1;
    }
    if ($pendaftaran->status !== 'awaiting_payment') {
        $this->warn("Status pendaftaran saat ini \"{$pendaftaran->status}\", bukan menunggu pembayaran.");
    }

    $berubah = app(KonfirmasiPembayaran::class)->lunas(
        $pendaftaran->pembayaran,
        'SIMULASI LOKAL: pembayaran ditandai lunas tanpa DOKU.',
        'SIMULASI_LOKAL',
    );

    $berubah
        ? $this->info("Pembayaran {$pendaftaran->pembayaran->kode_transaksi} ditandai LUNAS (notifikasi diantrekan).")
        : $this->warn('Pembayaran sudah lunas sebelumnya, tidak ada perubahan.');

    return 0;
})->purpose('[LOKAL] Simulasikan pembayaran lunas tanpa DOKU');

// Tetapkan ulang hasil penilaian MUK yang semua dokumen asesmennya sudah selesai tapi status masih "berlangsung"
// (mis. tertahan aturan lama yang menunggu FR.AK.03). Aman dijalankan berulang.
Artisan::command('muk:sinkron-penyelesaian {--dry-run : Hanya tampilkan, tanpa mengubah}', function () {
    $workflow = app(DokumenWorkflow::class);
    $jumlah = 0;

    PenilaianMuk::where('status', PenilaianMuk::STATUS_BERLANGSUNG)->orderBy('id')->each(function (PenilaianMuk $p) use ($workflow, &$jumlah) {
        $pelaku = $p->jadwalAsesmen?->asesor ?? $p->ditentukanOlehAsesor;
        if (! $pelaku) {
            return;
        }

        if ($this->option('dry-run')) {
            // Hanya cek syarat (sama dengan DokumenWorkflow::sinkronPenyelesaian), tanpa menulis.
            $dokumen = $p->dokumenPenilaian()->with('jenisDokumen')->get();
            $ak02 = $dokumen->first(fn ($d) => $d->jenisDokumen->kode === JenisDokumenMuk::AK_02);
            $lengkap = $dokumen
                ->reject(fn ($d) => in_array($d->jenisDokumen->kode, JenisDokumenMuk::PASCA_KEPUTUSAN, true))
                ->every(fn ($d) => $d->status === DokumenPenilaian::STATUS_SELESAI);
            $rekomendasi = $ak02 ? Ak02Handler::keputusan($ak02)['rekomendasi'] : null;
            if ($ak02 && $lengkap && $rekomendasi !== null) {
                $jumlah++;
                $this->line("Penilaian #{$p->id}: akan menjadi {$rekomendasi} (dry-run)");
            }

            return;
        }

        $hasil = DB::transaction(function () use ($p, $pelaku, $workflow) {
            $kunci = PenilaianMuk::lockForUpdate()->findOrFail($p->id);
            $workflow->sinkronPenyelesaian($kunci, $pelaku);

            return $kunci->status;
        });

        if ($hasil !== PenilaianMuk::STATUS_BERLANGSUNG) {
            $jumlah++;
            $this->line("Penilaian #{$p->id}: {$hasil}");
        }
    });

    $this->info("{$jumlah} penilaian ditetapkan hasilnya.");
})->purpose('Tetapkan hasil penilaian MUK yang dokumen asesmennya sudah lengkap');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
