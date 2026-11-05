<?php

namespace App\Notifications;

use App\Models\BuktiPembayaran;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Ke admin LSP: asesi mengunggah bukti transfer manual, perlu diverifikasi di CMS. */
class BuktiPembayaranMasukNotifikasi extends Notification implements ShouldQueue
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
        $p = $this->bukti->loadMissing('pembayaran.pendaftaranSertifikasi.asesi', 'pembayaran.pendaftaranSertifikasi.skemaSertifikasi')->pembayaran;
        $pendaftaran = $p->pendaftaranSertifikasi;

        return (new MailMessage)
            ->subject('Bukti Transfer Menunggu Verifikasi')
            ->line('Asesi '.($pendaftaran->asesi->name ?? '-').' mengunggah bukti transfer untuk skema '.($pendaftaran->skemaSertifikasi->title['id'] ?? '-').'.')
            ->line('Tagihan: Rp '.number_format((float) $p->jumlah, 0, ',', '.').' | Ditransfer: Rp '.number_format((float) $this->bukti->jumlah_transfer, 0, ',', '.'))
            ->line('Pengirim: '.$this->bukti->nama_pengirim.' ('.$this->bukti->bank_pengirim.')')
            ->action('Verifikasi Pembayaran', route('verifikasi-pembayaran.show', $this->bukti));
    }

    public function toArray(object $notifiable): array
    {
        $pendaftaran = $this->bukti->loadMissing('pembayaran.pendaftaranSertifikasi.asesi')->pembayaran->pendaftaranSertifikasi;

        return [
            'bukti_pembayaran_id' => $this->bukti->id,
            'kode_pendaftaran' => $pendaftaran->kode_pendaftaran,
            'status' => 'bukti_pembayaran',
            'message' => 'Bukti transfer dari '.($pendaftaran->asesi->name ?? 'asesi').' menunggu verifikasi.',
            'url' => route('verifikasi-pembayaran.show', $this->bukti),
        ];
    }
}
