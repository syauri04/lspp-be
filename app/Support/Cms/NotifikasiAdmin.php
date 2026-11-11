<?php

namespace App\Support\Cms;

use App\Models\User;
use App\Notifications\Admin\PemberitahuanAdmin;
use App\Notifications\BuktiPembayaranMasukNotifikasi;
use App\Notifications\Muk\DokumenSiapDitinjauNotifikasi;
use App\Notifications\PendaftaranMasukNotifikasi;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Titik tunggal notifikasi lonceng CMS:
 * - kirim() ke semua admin/super-admin;
 * - tampil() mengubah baris tabel notifications (format lama maupun baru) menjadi data tampilan
 *   yang seragam, sehingga notifikasi lama tetap tampil rapi tanpa migrasi data.
 */
class NotifikasiAdmin
{
    public const PERAN = ['admin', 'super-admin'];

    /** Kategori -> [ikon mdi, warna bootstrap]. */
    public const GAYA = [
        'pendaftaran' => ['mdi-file-document-edit-outline', 'primary'],
        'bukti_pembayaran' => ['mdi-cash-check', 'warning'],
        'pembayaran' => ['mdi-credit-card-check-outline', 'success'],
        'penjadwalan' => ['mdi-calendar-clock', 'info'],
        'apl01' => ['mdi-clipboard-text-search-outline', 'danger'],
        'asesmen' => ['mdi-check-decagram-outline', 'success'],
        'sertifikat' => ['mdi-certificate-outline', 'warning'],
        'lain' => ['mdi-bell-outline', 'secondary'],
    ];

    public static function kirim(Notification $notifikasi): void
    {
        // whereHas (bukan User::role()) agar tidak error bila salah satu role belum dibuat.
        User::whereHas('roles', fn ($q) => $q->whereIn('name', self::PERAN))
            ->get()
            ->each(fn (User $admin) => $admin->notify($notifikasi));
    }

    public static function pemberitahuan(string $kategori, string $judul, string $pesan, ?string $url = null): void
    {
        self::kirim(new PemberitahuanAdmin($kategori, $judul, $pesan, $url));
    }

    /** @return array{id:string, kategori:string, judul:string, pesan:string, url:?string, ikon:string, warna:string, dibaca:bool, waktu:Carbon} */
    public static function tampil(DatabaseNotification $n): array
    {
        $d = $n->data ?? [];

        [$kategori, $judul] = match ($n->type) {
            PendaftaranMasukNotifikasi::class => ['pendaftaran', 'Pendaftaran baru perlu direview'],
            BuktiPembayaranMasukNotifikasi::class => ['bukti_pembayaran', 'Bukti transfer perlu diverifikasi'],
            DokumenSiapDitinjauNotifikasi::class => ['apl01', ($d['kode'] ?? 'Dokumen').' perlu direview'],
            default => [$d['kategori'] ?? 'lain', $d['judul'] ?? 'Notifikasi'],
        };
        $kategori = $d['kategori'] ?? $kategori;
        [$ikon, $warna] = self::GAYA[$kategori] ?? self::GAYA['lain'];

        // Notifikasi pendaftaran lama tidak menyimpan url: susun dari kode pendaftaran.
        $url = $d['url'] ?? (isset($d['kode_pendaftaran']) && $n->type === PendaftaranMasukNotifikasi::class
            ? route('pendaftaran.show', $d['kode_pendaftaran'])
            : null);

        return [
            'id' => $n->id,
            'kategori' => $kategori,
            'judul' => $d['judul'] ?? $judul,
            'pesan' => $d['pesan'] ?? $d['message'] ?? '',
            'url' => self::urlAman($url),
            'ikon' => $ikon,
            'warna' => $warna,
            'dibaca' => $n->read_at !== null,
            'waktu' => $n->created_at,
        ];
    }

    /**
     * Hanya URL di dalam aplikasi ini (mencegah open redirect dari data notifikasi). URL absolut
     * dibuat ulang memakai host yang sedang dipakai (worker antrean menyusun URL dari APP_URL).
     */
    public static function urlAman(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return url($url);
        }

        $bagian = parse_url($url);
        $hostDikenal = array_filter([parse_url((string) config('app.url'), PHP_URL_HOST), request()?->getHost()]);
        if (! isset($bagian['host']) || ! in_array($bagian['host'], $hostDikenal, true)
            || ! in_array($bagian['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }

        return url(($bagian['path'] ?? '/').(isset($bagian['query']) ? '?'.$bagian['query'] : ''));
    }
}
