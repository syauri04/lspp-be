<?php

namespace App\Notifications;

use App\Models\PendaftaranSertifikasi;
use App\Models\PenilaianMuk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ke asesi: perubahan status pendaftaran yang belum punya notifikasi khusus
 * (dipicu RiwayatStatusPendaftaranObserver, lihat NotifikasiPendaftaran::STATUS).
 *  - submitted       : konfirmasi pendaftaran diterima sistem, menunggu verifikasi admin
 *  - paid            : pembayaran berhasil, menunggu jadwal asesmen
 *  - expired         : batas waktu pembayaran lewat
 *  - asesmen_selesai : hasil asesmen (kompeten / belum kompeten) dari MUK
 * Payload database mengikuti format standar notifikasi asesi (NotifikasiAsesiResource).
 */
class StatusPendaftaranNotifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public PendaftaranSertifikasi $pendaftaran, public string $status)
    {
        // Dipicu di dalam transaksi perubahan status: kirim setelah commit.
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isi = $this->isi();
        $mail = (new MailMessage)
            ->subject($isi['subjek'])
            ->greeting('Halo, '.($notifiable->name ?? '').'!');
        foreach ($isi['baris'] as $baris) {
            $mail->line($baris);
        }

        return $mail->action($isi['tombol'], $this->urlAbsolut($isi['url']));
    }

    public function toArray(object $notifiable): array
    {
        $isi = $this->isi();

        return [
            'kategori' => $this->status === 'asesmen_selesai' ? 'asesmen' : ($this->status === 'submitted' ? 'pendaftaran' : 'pembayaran'),
            'judul' => $isi['judul'],
            'pesan' => $isi['pesan'],
            'skema' => $this->skema(),
            'kode_pendaftaran' => $this->pendaftaran->kode_pendaftaran,
            'url' => $isi['url'],
            'status' => $this->status,
        ];
    }

    /** @return array{judul: string, subjek: string, pesan: string, baris: string[], tombol: string, url: string} */
    private function isi(): array
    {
        $skema = $this->skema() ?? 'sertifikasi';
        $profil = '/profile';

        return match ($this->status) {
            'submitted' => [
                'judul' => 'Pendaftaran diterima',
                'subjek' => 'Pendaftaran Sertifikasi Anda Telah Kami Terima',
                'pesan' => "Pendaftaran {$skema} Anda telah kami terima dan sedang diverifikasi admin LSP.",
                'baris' => ["Pendaftaran {$skema} Anda telah kami terima.", 'Admin LSP akan memverifikasi dokumen Anda. Hasil verifikasi akan dikirim melalui email dan halaman profil.'],
                'tombol' => 'Lihat Status Pendaftaran',
                'url' => $profil,
            ],
            'paid' => [
                'judul' => 'Pembayaran berhasil',
                'subjek' => 'Pembayaran Sertifikasi Berhasil',
                'pesan' => "Pembayaran {$skema} Anda berhasil dikonfirmasi. Silakan menunggu jadwal asesmen dari admin LSP.",
                'baris' => ["Pembayaran {$skema} Anda berhasil dikonfirmasi.", 'Admin LSP akan menentukan jadwal asesmen dan mengabari Anda.'],
                'tombol' => 'Lihat Status Pendaftaran',
                'url' => $profil,
            ],
            'expired' => [
                'judul' => 'Batas waktu pembayaran habis',
                'subjek' => 'Batas Waktu Pembayaran Sertifikasi Telah Habis',
                'pesan' => "Batas waktu pembayaran {$skema} telah habis. Silakan ajukan pendaftaran kembali bila masih ingin mengikuti sertifikasi.",
                'baris' => ["Batas waktu pembayaran {$skema} telah habis sehingga pendaftaran tidak dapat dilanjutkan.", 'Silakan ajukan pendaftaran kembali bila masih ingin mengikuti sertifikasi.'],
                'tombol' => 'Lihat Skema Sertifikasi',
                'url' => '/sertifikasi',
            ],
            default => $this->isiHasil($skema, $profil),
        };
    }

    private function isiHasil(string $skema, string $profil): array
    {
        $kompeten = $this->pendaftaran->penilaianMuk?->status === PenilaianMuk::STATUS_SELESAI_KOMPETEN;
        $hasil = $kompeten ? 'KOMPETEN' : 'BELUM KOMPETEN';
        $lanjut = $kompeten
            ? 'Sertifikat Anda akan diproses oleh LSP.'
            : 'Silakan lihat umpan balik asesor di dokumen asesmen Anda.';

        return [
            'judul' => 'Hasil asesmen',
            'subjek' => "Hasil Asesmen Sertifikasi: {$hasil}",
            'pesan' => "Asesmen {$skema} telah selesai. Anda dinyatakan {$hasil}. {$lanjut}",
            'baris' => ["Asesmen {$skema} telah selesai.", "Hasil: Anda dinyatakan {$hasil}.", $lanjut],
            'tombol' => 'Lihat Hasil',
            'url' => $profil,
        ];
    }

    private function skema(): ?string
    {
        return $this->pendaftaran->skemaSertifikasi?->title['id'] ?? null;
    }

    /** Path website (tanpa bahasa) -> URL lspp-fe absolut untuk tombol email. */
    private function urlAbsolut(string $path): string
    {
        return rtrim(config('app.frontend_url'), '/').'/id'.$path;
    }
}
