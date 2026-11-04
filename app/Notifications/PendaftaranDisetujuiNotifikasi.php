<?php

namespace App\Notifications;

use App\Models\PendaftaranSertifikasi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendaftaranDisetujuiNotifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PendaftaranSertifikasi $pendaftaran) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        // Link ke halaman FE Next.js, BUKAN ke backend Laravel -- FE yang render halaman checkout
        // dan yang manggil API /api/checkout/{kode_pendaftaran} pakai token asesi tersimpan di browser.
        // Otorisasi kepemilikan tetap dicek di API (lihat CheckoutController::asesiAtauTolak),
        // jadi link ini aman meski tidak pakai signed URL -- kode_pendaftaran sudah UUID yang tidak bisa ditebak.
        $url = rtrim(config('app.frontend_url'), '/').'/checkout/'.$this->pendaftaran->kode_pendaftaran;

        return (new MailMessage)
            ->subject('Pendaftaran Sertifikasi Anda Disetujui')
            ->line('Selamat, dokumen pendaftaran sertifikasi Anda telah disetujui.')
            ->line('Silakan lanjutkan ke pembayaran.')
            ->action('Bayar Sekarang', $url);
    }

    public function toArray($notifiable): array
    {
        // Format standar notifikasi asesi (lihat NotifikasiAsesiResource); "message" & "status" untuk kompatibilitas.
        $skema = $this->pendaftaran->skemaSertifikasi?->title['id'] ?? null;
        $pesan = 'Pendaftaran sertifikasi'.($skema ? " {$skema}" : '').' Anda disetujui. Silakan lakukan pembayaran untuk melanjutkan proses sertifikasi.';

        return [
            'kategori' => 'pendaftaran',
            'judul' => 'Pendaftaran disetujui',
            'pesan' => $pesan,
            'skema' => $skema,
            'kode_pendaftaran' => $this->pendaftaran->kode_pendaftaran,
            'url' => '/checkout/'.$this->pendaftaran->kode_pendaftaran,
            'pendaftaran_id' => $this->pendaftaran->id,
            'status' => 'awaiting_payment',
            'message' => $pesan,
        ];
    }
}
