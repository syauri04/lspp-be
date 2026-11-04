<?php

namespace App\Http\Resources\Profil;

use App\Notifications\PendaftaranDisetujuiNotifikasi;
use App\Notifications\PendaftaranDitolakNotifikasi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notifikasi (channel database) milik asesi untuk halaman Profil / Notifikasi lspp-fe.
 *
 * Format standar payload toArray() notifikasi asesi:
 *   { kategori, judul, pesan, skema, kode_pendaftaran, url }
 * url: path website tanpa prefix bahasa (mis. "/checkout/{kode}") atau URL absolut (mis. MUK).
 * Payload lama ({ message, status, kode_pendaftaran }) tetap didukung lewat fallback per tipe.
 *
 * @mixin DatabaseNotification
 */
class NotifikasiAsesiResource extends JsonResource
{
    /** Fallback untuk notifikasi yang tersimpan sebelum format standar: tipe => [kategori, judul]. */
    private const BAWAAN = [
        PendaftaranDisetujuiNotifikasi::class => ['pendaftaran', 'Pendaftaran disetujui'],
        PendaftaranDitolakNotifikasi::class => ['pendaftaran', 'Pendaftaran ditolak'],
    ];

    public function toArray(Request $request): array
    {
        $data = (array) $this->data;
        [$kategori, $judul] = self::BAWAAN[$this->type] ?? ['umum', 'Notifikasi'];
        $kode = $data['kode_pendaftaran'] ?? null;

        return [
            'id' => $this->id,
            'kategori' => $data['kategori'] ?? $kategori,
            'judul' => $data['judul'] ?? $judul,
            'pesan' => $data['pesan'] ?? $data['message'] ?? '',
            'skema' => $data['skema'] ?? null,
            'kode_pendaftaran' => $kode,
            'url' => $data['url'] ?? (($data['status'] ?? null) === 'awaiting_payment' && $kode ? "/checkout/{$kode}" : null),
            'dibaca' => $this->read_at !== null,
            'dibaca_at' => $this->read_at?->toIso8601String(),
            'dibuat_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
