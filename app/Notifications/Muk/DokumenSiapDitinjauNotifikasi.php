<?php

namespace App\Notifications\Muk;

use App\Models\DokumenPenilaian;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ke peninjau (admin LSP / asesor): ada dokumen MUK yang siap ditinjau.
 * Admin menerima juga notifikasi dashboard (database), sama seperti PendaftaranMasukNotifikasi.
 */
class DokumenSiapDitinjauNotifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        protected DokumenPenilaian $dokumen,
        protected string $url,
        protected bool $denganDatabase = false,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $this->denganDatabase ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->dokumen->loadMissing(['jenisDokumen', 'penilaianMuk.pendaftaran.asesi', 'penilaianMuk.pendaftaran.skemaSertifikasi', 'penilaianMuk.jadwalAsesmen']);
        $kode = $d->jenisDokumen->kode;
        $pendaftaran = $d->penilaianMuk->pendaftaran;

        return (new MailMessage)
            ->subject("{$kode} Menunggu Tinjauan Anda")
            ->greeting('Halo, '.($notifiable->nama ?? $notifiable->name).'!')
            ->line("{$kode} dari asesi berikut siap Anda tinjau.")
            ->line('Asesi: '.$pendaftaran->asesi->name)
            ->line('Skema: '.($pendaftaran->skemaSertifikasi->title['id'] ?? '-'))
            ->when($d->penilaianMuk->jadwalAsesmen, fn (MailMessage $m) => $m->line('Tanggal asesmen: '.$d->penilaianMuk->jadwalAsesmen->tanggal_asesmen->format('d F Y')))
            ->action("Tinjau {$kode}", $this->url);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'dokumen_penilaian_id' => $this->dokumen->id,
            'kode' => $this->dokumen->jenisDokumen->kode,
            'message' => "{$this->dokumen->jenisDokumen->kode} dari {$this->dokumen->penilaianMuk->pendaftaran->asesi->name} menunggu tinjauan.",
            'url' => $this->url,
        ];
    }
}
