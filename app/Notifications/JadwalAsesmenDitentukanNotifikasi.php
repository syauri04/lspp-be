<?php

namespace App\Notifications;

use App\Models\Asesi;
use App\Models\JadwalAsesmen;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JadwalAsesmenDitentukanNotifikasi extends Notification implements ShouldQueue
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
        // Database untuk halaman profil/notifikasi asesi di website.
        return $notifiable instanceof Asesi ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $jadwal = $this->jadwal->load(['pendaftaran.skemaSertifikasi', 'asesor', 'tuk', 'penilaianMuk']);

        return (new MailMessage)
            ->subject('Jadwal Asesmen Sertifikasi Anda Sudah Ditentukan')
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line('Jadwal asesmen untuk pendaftaran sertifikasi Anda sudah ditentukan.')
            ->line('Skema: '.$jadwal->pendaftaran->skemaSertifikasi->title['id'])
            ->line('Asesor: '.$jadwal->asesor->nama)
            ->line('Tanggal: '.$jadwal->tanggal_asesmen->format('d F Y'))
            ->line('Waktu: '.$jadwal->waktu_mulai.(($jadwal->waktu_selesai) ? ' - '.$jadwal->waktu_selesai : ''))
            ->line('Lokasi TUK: '.$jadwal->tuk->title.' ('.$jadwal->tuk->address.')')
            ->when($jadwal->catatan, fn ($mail) => $mail->line('Catatan: '.$jadwal->catatan))
            ->line('Mohon hadir tepat waktu sesuai jadwal di atas.')
            // Deep link MUK: tidak memberi akses, asesi tetap harus login.
            ->when($jadwal->penilaianMuk, fn ($mail) => $mail
                ->line('Sebelum asesmen, lengkapi dokumen FR.APL.01 dan FR.APL.02 di website sertifikasi.')
                ->action('Isi Dokumen Sertifikasi', config('muk.frontend_url').'/dashboard/penilaian/'.$jadwal->penilaianMuk->id));
    }

    /** Format standar notifikasi asesi (NotifikasiAsesiResource). */
    public function toArray(object $notifiable): array
    {
        $jadwal = $this->jadwal->loadMissing(['pendaftaran.skemaSertifikasi', 'tuk', 'penilaianMuk']);
        $skema = $jadwal->pendaftaran->skemaSertifikasi->title['id'] ?? null;
        $waktu = $jadwal->tanggal_asesmen->locale('id')->translatedFormat('d F Y').($jadwal->waktu_mulai ? ' pukul '.substr((string) $jadwal->waktu_mulai, 0, 5).' WIB' : '');

        return [
            'kategori' => 'jadwal',
            'judul' => 'Jadwal asesmen ditentukan',
            'pesan' => 'Asesmen '.($skema ?? 'sertifikasi')." Anda dijadwalkan {$waktu} di ".($jadwal->tuk?->title ?? 'TUK').'. Lengkapi FR.APL.01 & FR.APL.02 di website sertifikasi sebelum asesmen.',
            'skema' => $skema,
            'kode_pendaftaran' => $jadwal->pendaftaran->kode_pendaftaran,
            'url' => $jadwal->penilaianMuk ? config('muk.frontend_url').'/dashboard/penilaian/'.$jadwal->penilaianMuk->id : '/profile',
            'jadwal_id' => $jadwal->id,
        ];
    }
}
