<?php

namespace App\Notifications;

use App\Models\PendaftaranSertifikasi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendaftaranMasukNotifikasi extends Notification implements ShouldQueue
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
            ->subject('Pendaftaran Sertifikasi Baru Menunggu Review')
            ->line("Asesi {$this->pendaftaran->asesi->nama} mengajukan pendaftaran untuk skema {$this->pendaftaran->skemaSertifikasi->nama_skema}.")
            ->action('Review Pendaftaran', url("/admin/pendaftaran/{$this->pendaftaran->kode_pendaftaran}"));
    }

    public function toArray($notifiable): array
    {
        return [
            'pendaftaran_id' => $this->pendaftaran->id,
            'kode_pendaftaran' => $this->pendaftaran->kode_pendaftaran,
            'status' => 'submitted',
            'message' => "Pendaftaran baru dari {$this->pendaftaran->asesi->nama} menunggu review.",
        ];
    }
}
