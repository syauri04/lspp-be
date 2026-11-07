<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi lonceng CMS untuk admin (database saja, tanpa email) dengan payload standar
 * {kategori, judul, pesan, url}. Dipakai untuk kejadian yang perlu ditindaklanjuti admin
 * tetapi tidak perlu email (mis. pembayaran lunas -> perlu dijadwalkan).
 */
class PemberitahuanAdmin extends Notification
{
    use Queueable;

    public function __construct(
        public string $kategori,
        public string $judul,
        public string $pesan,
        public ?string $url = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kategori' => $this->kategori,
            'judul' => $this->judul,
            'pesan' => $this->pesan,
            'url' => $this->url,
        ];
    }
}
