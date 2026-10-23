<?php

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\PenilaianMuk;
use App\Services\Muk\Dokumen\Ak02Handler;
use App\Services\Muk\DokumenWorkflow;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

// Hapus token Sanctum yang sudah kedaluwarsa > 24 jam (token MUK berumur 8 jam).
Schedule::command('sanctum:prune-expired --hours=24')->daily();

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
