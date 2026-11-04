<?php

namespace App\Notifications\Muk;

use App\Models\Asesi;
use App\Models\DokumenPenilaian;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ke asesi: hasil tinjauan dokumen MUK (diterima / dikembalikan untuk revisi).
 */
class DokumenDitinjauNotifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        protected DokumenPenilaian $dokumen,
        protected bool $positif,
        protected string $peninjau, // "admin LSP" / "asesor"
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof Asesi ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->dokumen->loadMissing(['jenisDokumen', 'penilaianMuk.pendaftaran.skemaSertifikasi']);
        $kode = $d->jenisDokumen->kode;
        $skema = $d->penilaianMuk->pendaftaran->skemaSertifikasi->title['id'] ?? '-';
        $catatan = $d->catatan_admin ?? $d->catatan_asesor;
        $url = $this->urlDokumen();

        $mail = (new MailMessage)
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line('Skema: '.$skema);

        if ($this->positif) {
            $mail->subject("{$kode} Anda Telah Ditinjau")
                ->line("{$kode} Anda telah ditinjau dan diterima oleh {$this->peninjau}.");
        } else {
            $mail->subject("{$kode} Perlu Diperbaiki")
                ->line("{$kode} Anda dikembalikan oleh {$this->peninjau} untuk diperbaiki.")
                ->when($catatan, fn (MailMessage $m) => $m->line('Catatan: '.$catatan))
                ->line('Silakan perbaiki, tanda tangani ulang, lalu kirim kembali.');
        }

        return $mail->action('Buka Dokumen Sertifikasi', $url);
    }

    /** Format standar notifikasi asesi (NotifikasiAsesiResource). */
    public function toArray(object $notifiable): array
    {
        $d = $this->dokumen->loadMissing(['jenisDokumen', 'penilaianMuk.pendaftaran.skemaSertifikasi']);
        $kode = $d->jenisDokumen->kode;
        $skema = $d->penilaianMuk->pendaftaran->skemaSertifikasi->title['id'] ?? null;
        $catatan = $d->catatan_admin ?? $d->catatan_asesor;

        return [
            'kategori' => 'asesmen',
            'judul' => $this->positif ? "{$kode} diterima" : "{$kode} perlu diperbaiki",
            'pesan' => $this->positif
                ? "{$kode} Anda telah ditinjau dan diterima oleh {$this->peninjau}."
                : "{$kode} Anda dikembalikan oleh {$this->peninjau} untuk diperbaiki.".($catatan ? " Catatan: {$catatan}" : ''),
            'skema' => $skema,
            'kode_pendaftaran' => $d->penilaianMuk->pendaftaran->kode_pendaftaran,
            'url' => $this->urlDokumen(),
            'dokumen' => $kode,
        ];
    }

    private function urlDokumen(): string
    {
        return config('muk.frontend_url').'/dashboard/penilaian/'.$this->dokumen->penilaian_muk_id.'/dokumen/'.$this->dokumen->jenisDokumen->slug;
    }
}
