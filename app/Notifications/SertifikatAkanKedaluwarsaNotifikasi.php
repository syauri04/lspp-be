<?php

namespace App\Notifications;

use App\Models\Asesi;
use App\Models\Sertifikat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pengingat e-sertifikat akan kedaluwarsa (dikirim perintah terjadwal `sertifikat:kirim-pengingat-kedaluwarsa`):
 * - ke asesi pemegang: email + notifikasi website (profil),
 * - ke admin/super admin: email + lonceng CMS (tautan ke halaman penerbitan).
 */
class SertifikatAkanKedaluwarsaNotifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public Sertifikat $sertifikat) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->sertifikat;
        $mail = (new MailMessage)
            ->subject('Pengingat: E-Sertifikat Akan Kedaluwarsa '.$this->tanggal())
            ->greeting('Halo, '.($notifiable->name ?? $notifiable->nama ?? '').'!');

        if ($notifiable instanceof Asesi) {
            return $mail
                ->line('E-Sertifikat kompetensi Anda akan berakhir masa berlakunya pada '.$this->tanggal().'.')
                ->line('Skema: '.$this->skema())
                ->line("No. Sertifikat: {$s->no_sertifikat}")
                ->line('Silakan hubungi LSP untuk informasi sertifikasi ulang (resertifikasi) agar kompetensi Anda tetap tercatat berlaku.')
                ->action('Lihat Profil', $this->urlAsesi());
        }

        return $mail
            ->line('E-Sertifikat berikut akan kedaluwarsa pada '.$this->tanggal().':')
            ->line("Pemegang: {$s->nama_pemegang}")
            ->line('Skema: '.$this->skema())
            ->line("No. Blanko: {$s->no_blanko}")
            ->line("No. Sertifikat: {$s->no_sertifikat}")
            ->action('Buka Penerbitan Sertifikat', route('penerbitan-sertifikat.show', $s->penerbitan_sertifikasi_id));
    }

    public function toArray(object $notifiable): array
    {
        $s = $this->sertifikat;
        $pesan = "E-Sertifikat {$s->no_sertifikat} (".$this->skema().') berakhir pada '.$this->tanggal().'.';

        if ($notifiable instanceof Asesi) {
            return [
                'kategori' => 'sertifikat',
                'judul' => 'E-Sertifikat akan kedaluwarsa',
                'pesan' => $pesan.' Hubungi LSP untuk sertifikasi ulang.',
                'skema' => $this->skema(),
                'kode_pendaftaran' => $s->penerbitan?->pendaftaran?->kode_pendaftaran,
                'url' => $this->urlAsesi(),
                'sertifikat_id' => $s->id,
            ];
        }

        return [
            'kategori' => 'sertifikat',
            'judul' => 'E-Sertifikat akan kedaluwarsa',
            'pesan' => "{$s->nama_pemegang}: {$pesan}",
            'url' => route('penerbitan-sertifikat.show', $s->penerbitan_sertifikasi_id, false),
            'sertifikat_id' => $s->id,
        ];
    }

    private function tanggal(): string
    {
        return $this->sertifikat->tanggal_expired->locale('id')->translatedFormat('d F Y');
    }

    private function skema(): string
    {
        return $this->sertifikat->skema_judul['id'] ?? $this->sertifikat->skema_nomor ?? 'sertifikasi';
    }

    private function urlAsesi(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/id/profile';
    }
}
