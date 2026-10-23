<?php

namespace App\Notifications\Muk;

use App\Models\DokumenPenilaian;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alur persetujuan dokumen MUK (mis. FR.AK.07 / FR.AK.01):
 * - MINTA     : ke asesi — asesor sudah mengisi & menandatangani, menunggu tanda tangan asesi.
 * - DISETUJUI : ke asesor — asesi sudah menyetujui (dokumen selesai).
 * - JAWAB     : ke asesi — dokumen dibuka asesor untuk dijawab.
 * - DIJAWAB   : ke asesor — asesi sudah mengirim jawaban untuk dinilai.
 * - DINILAI   : ke asesi — asesor telah menilai jawaban.
 */
class DokumenPersetujuanNotifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    public const MINTA = 'minta';

    public const DISETUJUI = 'disetujui';

    /** Ke asesi: asesor membuka dokumen untuk dijawab (mis. pertanyaan FR.IA.03). */
    public const JAWAB = 'jawab';

    /** Ke asesor: asesi sudah mengirim jawaban untuk dinilai. */
    public const DIJAWAB = 'dijawab';

    /** Ke asesi: asesor telah menilai jawaban. */
    public const DINILAI = 'dinilai';

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        protected DokumenPenilaian $dokumen,
        protected string $jenis,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->dokumen->loadMissing(['jenisDokumen', 'penilaianMuk.pendaftaran.skemaSertifikasi']);
        $kode = $d->jenisDokumen->kode;
        $nama = $d->jenisDokumen->nama;
        $skema = $d->penilaianMuk->pendaftaran->skemaSertifikasi->title['id'] ?? '-';
        $url = config('muk.frontend_url').'/penilaian/'.$d->penilaian_muk_id.'/dokumen/'.$d->jenisDokumen->slug;

        $mail = (new MailMessage)
            ->greeting('Halo, '.($notifiable->name ?? $notifiable->nama ?? '').'!')
            ->line('Skema: '.$skema);

        match ($this->jenis) {
            self::MINTA => $mail->subject("{$kode} Menunggu Persetujuan Anda")
                ->line("Asesor telah mengisi {$kode} {$nama}.")
                ->line('Silakan baca isinya, lalu setujui dengan tanda tangan Anda.'),
            self::JAWAB => $mail->subject("{$kode} Siap Dijawab")
                ->line("Asesor telah membuka {$kode} {$nama} untuk Anda jawab.")
                ->line('Silakan tuliskan tanggapan setiap pertanyaan, tanda tangani, lalu kirim.'),
            self::DIJAWAB => $mail->subject("{$kode} Siap Dinilai")
                ->line("Asesi telah mengirim jawaban {$kode} {$nama}.")
                ->line('Silakan nilai setiap jawaban dan tanda tangani.'),
            self::DINILAI => $mail->subject("{$kode} Telah Dinilai")
                ->line("Asesor telah menilai jawaban {$kode} {$nama} Anda."),
            default => $mail->subject("{$kode} Telah Disetujui Asesi")
                ->line("Asesi telah menyetujui dan menandatangani {$kode} {$nama}."),
        };

        return $mail->action('Buka Dokumen', $url);
    }
}
