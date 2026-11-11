<?php

namespace App\Notifications\Muk;

use App\Models\DokumenPenilaian;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Ke asesor: super admin LSP mengoreksi isi dokumen MUK yang sudah dikirim (beserta alasannya). */
class DokumenDikoreksiNotifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        protected DokumenPenilaian $dokumen,
        protected string $alasan,
        protected int $jumlahPerubahan,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->dokumen->loadMissing(['jenisDokumen', 'penilaianMuk.pendaftaran.asesi', 'penilaianMuk.pendaftaran.skemaSertifikasi']);
        $kode = $d->jenisDokumen->kode;
        $pendaftaran = $d->penilaianMuk->pendaftaran;

        return (new MailMessage)
            ->subject("{$kode} Dikoreksi Admin LSP")
            ->greeting('Halo, '.($notifiable->name ?? $notifiable->nama ?? '').'!')
            ->line("Admin LSP telah mengoreksi isi {$kode} {$d->jenisDokumen->nama}.")
            ->line('Asesi: '.($pendaftaran->asesi->nama ?? $pendaftaran->asesi->name ?? '-'))
            ->line('Skema: '.($pendaftaran->skemaSertifikasi->title['id'] ?? '-'))
            ->line("Jumlah isian yang berubah: {$this->jumlahPerubahan}")
            ->line('Alasan koreksi: '.$this->alasan)
            ->action('Lihat Dokumen', config('muk.frontend_url').'/dashboard/penilaian/'.$d->penilaian_muk_id.'/dokumen/'.$d->jenisDokumen->slug);
    }
}
