<?php

namespace App\Notifications;

use App\Models\JadwalAsesmen;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JadwalAsesmenUntukAsesorNotifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(protected JadwalAsesmen $jadwal) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $jadwal = $this->jadwal->load(['pendaftaran.asesi', 'pendaftaran.skemaSertifikasi', 'tuk', 'penilaianMuk']);

        return (new MailMessage)
            ->subject('Penugasan Asesmen Sertifikasi Baru')
            ->greeting('Halo, '.$notifiable->nama.'!')
            ->line('Anda ditugaskan sebagai asesor untuk asesmen sertifikasi berikut.')
            ->line('Skema: '.$jadwal->pendaftaran->skemaSertifikasi->title['id'])
            ->line('Asesi: '.$jadwal->pendaftaran->asesi->name)
            ->line('Tanggal: '.$jadwal->tanggal_asesmen->format('d F Y'))
            ->line('Waktu: '.$jadwal->waktu_mulai.(($jadwal->waktu_selesai) ? ' - '.$jadwal->waktu_selesai : ''))
            ->line('Lokasi TUK: '.$jadwal->tuk->title.' ('.$jadwal->tuk->address.')')
            ->when($jadwal->catatan, fn ($mail) => $mail->line('Catatan: '.$jadwal->catatan))
            // Deep link MUK: tidak memberi akses, asesor tetap harus login.
            ->when($jadwal->penilaianMuk, fn ($mail) => $mail
                ->action('Buka Dokumen Sertifikasi', config('muk.frontend_url').'/dashboard/penilaian/'.$jadwal->penilaianMuk->id));
    }
}
