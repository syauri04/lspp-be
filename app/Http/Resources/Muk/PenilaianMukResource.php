<?php

namespace App\Http\Resources\Muk;

use App\Models\DokumenPenilaian;
use App\Models\PenilaianMuk;
use App\Services\Muk\AksesDokumen;
use App\Services\Muk\MetodeAsesmenService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PenilaianMuk
 */
class PenilaianMukResource extends JsonResource
{
    private bool $perluTindakan = false;

    private ?string $peranDetail = null;

    public function tandaiPerluTindakan(bool $nilai): static
    {
        $this->perluTindakan = $nilai;

        return $this;
    }

    /** Mode detail: dokumen dilengkapi status kunci & catatan untuk peran ini. */
    public function detailUntuk(string $peran): static
    {
        $this->peranDetail = $peran;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $pendaftaran = $this->pendaftaran;
        $skema = $pendaftaran->skemaSertifikasi;
        $jadwal = $this->jadwalAsesmen;

        $dokumen = $this->dokumenPenilaian->sortBy('jenisDokumen.urutan_default')->values();

        return [
            'id' => $this->id,
            'status' => $this->status,
            'perlu_tindakan' => $this->perluTindakan,
            'metode_asesmen' => $this->metode_asesmen,
            'metode_ditentukan_at' => $this->metode_ditentukan_at?->toIso8601String(),
            // Hanya di detail untuk asesor: boleh menentukan/mengubah metode sekarang?
            'aksi_metode' => $this->when($this->peranDetail === 'asesor', function () use ($dokumen) {
                $alasan = app(MetodeAsesmenService::class)->alasanTidakBisa($this->resource, $dokumen);

                return ['bisa' => $alasan === null, 'alasan' => $alasan];
            }),
            'skema' => [
                'id' => $skema->id,
                'judul' => $skema->title['id'] ?? null,
                'nomor' => $skema->nomor_skema,
                'jenis' => $skema->jenis_skema,
            ],
            'jadwal' => $jadwal ? [
                'tanggal' => $jadwal->tanggal_asesmen?->toDateString(),
                'waktu_mulai' => $jadwal->waktu_mulai,
                'waktu_selesai' => $jadwal->waktu_selesai,
                'status' => $jadwal->status,
                'tuk' => $jadwal->tuk ? [
                    'nama' => $jadwal->tuk->title,
                    'kota' => $jadwal->tuk->city,
                    'alamat' => $jadwal->tuk->address,
                ] : null,
            ] : null,
            'asesi' => ['nama' => $pendaftaran->asesi->name],
            'asesor' => $jadwal?->asesor ? [
                'nama' => $jadwal->asesor->nama,
                'nomor_registrasi' => $jadwal->asesor->nomor_registrasi,
            ] : null,
            'dokumen' => $dokumen->map(function (DokumenPenilaian $d) use ($dokumen, $request) {
                $item = new DokumenPenilaianRingkasResource($d);

                if ($this->peranDetail !== null) {
                    $item->denganAkses(app(AksesDokumen::class)->alasanTerkunci($d, $dokumen, $this->peranDetail));
                }

                return $item->resolve($request);
            }),
        ];
    }
}
