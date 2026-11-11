<?php

namespace App\Http\Resources\Sertifikat;

use App\Models\Sertifikat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Data e-sertifikat yang BOLEH tampil publik (verifikasi) & di profil asesi.
 * Sengaja tanpa data pribadi lain (NIK, email, telepon) maupun berkas PDF.
 *
 * @mixin Sertifikat
 */
class SertifikatPublikResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'nama' => $this->nama_pemegang,
            'no_blanko' => $this->no_blanko,
            'no_sertifikat' => $this->no_sertifikat,
            'skema' => [
                // {id, en} — dipilih sesuai bahasa di FE.
                'judul' => $this->skema_judul,
                'nomor' => $this->skema_nomor,
            ],
            'unit_kompetensi' => collect($this->unit_kompetensi ?? [])->map(fn ($u) => [
                'kode_unit' => $u['kode_unit'] ?? null,
                'judul_unit' => $u['judul_unit'] ?? null,
            ])->values()->all(),
            'tanggal_aktif' => $this->tanggal_aktif->toDateString(),
            'tanggal_expired' => $this->tanggal_expired->toDateString(),
            'masa_berlaku_tahun' => $this->masaBerlakuTahun(),
            'status' => $this->berlaku() ? Sertifikat::BERLAKU : Sertifikat::TIDAK_BERLAKU,
        ];
    }
}
