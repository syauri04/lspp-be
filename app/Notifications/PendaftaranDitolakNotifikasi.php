<?php

namespace App\Notifications;

use App\Models\PendaftaranSertifikasi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendaftaranDitolakNotifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PendaftaranSertifikasi $pendaftaran) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pendaftaran Sertifikasi Anda Perlu Ditinjau Ulang')
            ->line('Mohon maaf, pendaftaran sertifikasi Anda belum dapat kami setujui.')
            ->line('Alasan: ' . $this->pendaftaran->catatan_admin)
            ->line('Anda dapat mengajukan pendaftaran baru setelah melengkapi dokumen yang diminta.');
    }

    public function toArray($notifiable): array
    {
        return [
            'pendaftaran_id' => $this->pendaftaran->id,
            'kode_pendaftaran' => $this->pendaftaran->kode_pendaftaran,
            'status' => 'rejected',
            'message' => 'Pendaftaran Anda ditolak: ' . $this->pendaftaran->catatan_admin,
        ];
    }
}
