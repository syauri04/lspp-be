<?php

namespace App\Notifications\Muk;

use App\Models\DokumenPenilaian;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ke asesi: hasil tinjauan dokumen MUK (diterima / dikembalikan untuk revisi).
 */
class DokumenDitinjauNotifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        protected DokumenPenilaian $dokumen,
        protected bool $positif,
        protected string $peninjau, // "admin LSP" / "asesor"
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->dokumen->loadMissing(['jenisDokumen', 'penilaianMuk.pendaftaran.skemaSertifikasi']);
        $kode = $d->jenisDokumen->kode;
        $skema = $d->penilaianMuk->pendaftaran->skemaSertifikasi->title['id'] ?? '-';
        $catatan = $d->catatan_admin ?? $d->catatan_asesor;
        $url = config('muk.frontend_url').'/penilaian/'.$d->penilaian_muk_id;

        $mail = (new MailMessage)
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line('Skema: '.$skema);

        if ($this->positif) {
            $mail->subject("{$kode} Anda Telah Ditinjau")
                ->line("{$kode} Anda telah ditinjau dan diterima oleh {$this->peninjau}.");
        } else {
            $mail->subject("{$kode} Perlu Diperbaiki")
                ->line("{$kode} Anda dikembalikan oleh {$this->peninjau} untuk diperbaiki.")
                ->when($catatan, fn (MailMessage $m) => $m->line('Catatan: '.$catatan))
                ->line('Silakan perbaiki, tanda tangani ulang, lalu kirim kembali.');
        }

        return $mail->action('Buka Dokumen Sertifikasi', $url);
    }
}
