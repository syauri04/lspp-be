<?php

namespace App\Http\Resources\Profil;

use App\Models\PendaftaranSertifikasi;
use App\Models\PenilaianMuk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pendaftaran sertifikasi milik asesi untuk halaman Profil (lspp-fe).
 * List: ringkasan kartu. Detail (with timeline): + timeline gabungan & pembayaran.
 *
 * @mixin PendaftaranSertifikasi
 */
class PendaftaranAsesiResource extends JsonResource
{
    private const HASIL = [
        PenilaianMuk::STATUS_SELESAI_KOMPETEN => 'kompeten',
        PenilaianMuk::STATUS_SELESAI_BELUM_KOMPETEN => 'belum_kompeten',
    ];

    private bool $denganTimeline = false;

    public function denganTimeline(): static
    {
        $this->denganTimeline = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $skema = $this->skemaSertifikasi;
        $jadwal = $this->jadwalAsesmen?->status === 'dibatalkan' ? null : $this->jadwalAsesmen;
        $penilaian = $this->penilaianMuk;

        return [
            'kode_pendaftaran' => $this->kode_pendaftaran,
            'status' => $this->status,
            'status_label' => $this->menungguVerifikasi()
                ? 'Menunggu verifikasi pembayaran'
                : (PendaftaranSertifikasi::STATUS_LABEL[$this->status] ?? $this->status),
            'kelompok' => PendaftaranSertifikasi::kelompokDari($this->status),
            'catatan_admin' => $this->status === 'rejected' ? $this->catatan_admin : null,
            'diajukan_at' => ($this->submitted_at ?? $this->created_at)?->toIso8601String(),
            'skema' => $skema ? [
                'id' => $skema->id,
                // {id, en} — dipilih sesuai bahasa di FE.
                'judul' => $skema->title,
                'slug' => $skema->slug,
                'nomor' => $skema->nomor_skema,
                'gambar' => $skema->image ? asset($skema->image) : null,
            ] : null,
            'jadwal' => $jadwal ? [
                'tanggal' => $jadwal->tanggal_asesmen?->toDateString(),
                'waktu_mulai' => $jadwal->waktu_mulai ? substr((string) $jadwal->waktu_mulai, 0, 5) : null,
                'waktu_selesai' => $jadwal->waktu_selesai ? substr((string) $jadwal->waktu_selesai, 0, 5) : null,
                'status' => $jadwal->status,
                'tuk' => $jadwal->tuk ? [
                    'nama' => $jadwal->tuk->title,
                    'kota' => $jadwal->tuk->city,
                    'alamat' => $jadwal->tuk->address,
                ] : null,
                'asesor' => $jadwal->asesor?->nama,
            ] : null,
            'asesmen' => $penilaian ? [
                'status' => $penilaian->status,
                'metode' => $penilaian->metode_asesmen,
                'hasil' => self::HASIL[$penilaian->status] ?? null,
                'selesai_at' => $penilaian->selesai_at?->toIso8601String(),
            ] : null,
            'aksi' => $this->aksi($penilaian),
            ...($this->denganTimeline ? [
                'pembayaran' => $this->pembayaran ? [
                    'status' => $this->pembayaran->status,
                    'jumlah' => $this->pembayaran->jumlah,
                    'paid_at' => $this->pembayaran->paid_at?->toIso8601String(),
                    'expired_at' => $this->pembayaran->expired_at?->toIso8601String(),
                ] : null,
                'timeline' => $this->timeline(),
            ] : []),
        ];
    }

    /** Tombol aksi di kartu profil: bayar / mulai asesmen (MUK). */
    private function aksi(?PenilaianMuk $penilaian): ?array
    {
        if (in_array($this->status, ['approved', 'awaiting_payment'], true)) {
            // Bukti transfer manual sedang diverifikasi admin: arahkan ke halaman status, bukan bayar lagi.
            return [
                'jenis' => $this->menungguVerifikasi() ? 'cek_pembayaran' : 'bayar',
                'url' => '/checkout/'.$this->kode_pendaftaran,
            ];
        }
        if ($this->status === 'dijadwalkan' && $penilaian) {
            return ['jenis' => 'mulai_asesmen', 'url' => config('muk.frontend_url').'/dashboard/penilaian/'.$penilaian->id];
        }

        return null;
    }

    /** Menunggu pembayaran dan bukti transfer manual sedang diverifikasi admin. */
    private function menungguVerifikasi(): bool
    {
        return $this->status === 'awaiting_payment' && $this->pembayaran?->buktiMenunggu !== null;
    }

    /**
     * Timeline = riwayat status pendaftaran, urut waktu (hasil asesmen MUK sudah tercatat di
     * riwayat "asesmen_selesai" oleh SinkronStatusAsesmen).
     */
    private function timeline(): array
    {
        return $this->riwayatStatus->sortBy('created_at')->map(fn ($r) => [
            'status' => $r->status_ke,
            'judul' => PendaftaranSertifikasi::STATUS_LABEL[$r->status_ke] ?? $r->status_ke,
            'keterangan' => $r->keterangan,
            'tanggal' => $r->created_at?->toIso8601String(),
        ])->values()->all();
    }
}
