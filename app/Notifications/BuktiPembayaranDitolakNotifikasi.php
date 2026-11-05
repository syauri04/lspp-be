<?php

namespace App\Notifications;

use App\Models\BuktiPembayaran;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Ke asesi: bukti transfer manual ditolak admin (dengan alasan); asesi dapat mengunggah ulang. */
class BuktiPembayaranDitolakNotifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public BuktiPembayaran $bukti)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bukti Transfer Perlu Diperbaiki')
            ->greeting('Halo, '.($notifiable->name ?? '').'!')
            ->line('Bukti transfer pembayaran '.($this->skema() ?? 'sertifikasi').' Anda belum dapat kami verifikasi.')
            ->line('Alasan: '.$this->bukti->catatan_admin)
            ->line('Silakan unggah ulang bukti transfer yang benar melalui halaman pembayaran.')
            ->action('Unggah Ulang Bukti Transfer', rtrim(config('app.frontend_url'), '/').'/id'.$this->url());
    }

    /** Format standar notifikasi asesi (NotifikasiAsesiResource). */
    public function toArray(object $notifiable): array
    {
        $skema = $this->skema();

        return [
            'kategori' => 'pembayaran',
            'judul' => 'Bukti transfer ditolak',
            'pesan' => 'Bukti transfer pembayaran '.($skema ?? 'sertifikasi').' belum dapat diverifikasi. Alasan: '.$this->bukti->catatan_admin.' Silakan unggah ulang.',
            'skema' => $skema,
            'kode_pendaftaran' => $this->kode(),
            'url' => $this->url(),
        ];
    }

    private function skema(): ?string
    {
        return $this->bukti->loadMissing('pembayaran.pendaftaranSertifikasi.skemaSertifikasi')
            ->pembayaran->pendaftaranSertifikasi->skemaSertifikasi->title['id'] ?? null;
    }

    private function kode(): string
    {
        return $this->bukti->pembayaran->pendaftaranSertifikasi->kode_pendaftaran;
    }

    private function url(): string
    {
        return '/checkout/'.$this->kode();
    }
}
