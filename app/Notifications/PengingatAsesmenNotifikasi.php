<?php

namespace App\Notifications;

use App\Models\Asesi;
use App\Models\JadwalAsesmen;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ke asesi: pengingat asesmen H-{hari} (dikirim perintah terjadwal `asesmen:kirim-pengingat`).
 */
class PengingatAsesmenNotifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public JadwalAsesmen $jadwal, public int $hari) {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof Asesi ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $j = $this->jadwal->loadMissing(['pendaftaran.skemaSertifikasi', 'tuk', 'asesor', 'penilaianMuk']);

        return (new MailMessage)
            ->subject("Pengingat: Asesmen Sertifikasi {$this->kapan()}")
            ->greeting('Halo, '.($notifiable->name ?? '').'!')
            ->line('Asesmen '.$this->skema().' akan dilaksanakan '.$this->kapan().'.')
            ->line('Tanggal: '.$j->tanggal_asesmen->locale('id')->translatedFormat('d F Y').$this->jam())
            ->line('Lokasi TUK: '.($j->tuk?->title ?? '-').($j->tuk?->address ? " ({$j->tuk->address})" : ''))
            ->line('Asesor: '.($j->asesor?->nama ?? '-'))
            ->line('Pastikan dokumen FR.APL.01 & FR.APL.02 sudah lengkap dan hadir tepat waktu.')
            ->action('Buka Dokumen Sertifikasi', $this->url());
    }

    public function toArray(object $notifiable): array
    {
        $j = $this->jadwal->loadMissing(['pendaftaran.skemaSertifikasi', 'tuk', 'penilaianMuk']);

        return [
            'kategori' => 'jadwal',
            'judul' => 'Pengingat asesmen',
            'pesan' => 'Asesmen '.$this->skema().' akan dilaksanakan '.$this->kapan().' ('.$j->tanggal_asesmen->locale('id')->translatedFormat('d F Y').$this->jam().') di '.($j->tuk?->title ?? 'TUK').'.',
            'skema' => $this->skema(),
            'kode_pendaftaran' => $j->pendaftaran->kode_pendaftaran,
            'url' => $this->url(),
            'jadwal_id' => $j->id,
            'hari' => $this->hari,
        ];
    }

    private function kapan(): string
    {
        return $this->hari === 1 ? 'besok' : "{$this->hari} hari lagi";
    }

    private function jam(): string
    {
        return $this->jadwal->waktu_mulai ? ', pukul '.substr((string) $this->jadwal->waktu_mulai, 0, 5).' WIB' : '';
    }

    private function skema(): string
    {
        return $this->jadwal->pendaftaran->skemaSertifikasi->title['id'] ?? 'sertifikasi';
    }

    private function url(): string
    {
        return $this->jadwal->penilaianMuk
            ? config('muk.frontend_url').'/dashboard/penilaian/'.$this->jadwal->penilaianMuk->id
            : rtrim(config('app.frontend_url'), '/').'/id/profile';
    }
}
