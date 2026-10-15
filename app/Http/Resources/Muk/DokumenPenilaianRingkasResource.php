<?php

namespace App\Http\Resources\Muk;

use App\Models\DokumenPenilaian;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item menu "Dokumen Sertifikasi". Field detail (terkunci, catatan) hanya diisi di
 * endpoint detail penilaian lewat denganAkses().
 *
 * @mixin DokumenPenilaian
 */
class DokumenPenilaianRingkasResource extends JsonResource
{
    private bool $detail = false;

    private ?string $alasanTerkunci = null;

    public function denganAkses(?string $alasanTerkunci): static
    {
        $this->detail = true;
        $this->alasanTerkunci = $alasanTerkunci;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode' => $this->jenisDokumen->kode,
            'slug' => $this->jenisDokumen->slug,
            'nama' => $this->jenisDokumen->nama,
            'status' => $this->status,
            $this->mergeWhen($this->detail, fn () => [
                'terkunci' => $this->alasanTerkunci !== null,
                'alasan_terkunci' => $this->alasanTerkunci,
                'submitted_at' => $this->submitted_at?->toIso8601String(),
                'hasil_rekomendasi_asesor' => $this->hasil_rekomendasi_asesor,
                'catatan_asesor' => $this->catatan_asesor,
                'hasil_rekomendasi_admin' => $this->hasil_rekomendasi_admin,
                'catatan_admin' => $this->catatan_admin,
            ]),
        ];
    }
}
