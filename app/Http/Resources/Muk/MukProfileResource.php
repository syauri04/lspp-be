<?php

namespace App\Http\Resources\Muk;

use App\Http\Middleware\EnsureMukRole;
use App\Models\Asesi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Profil user MUK dengan bentuk seragam untuk asesi & asesor,
 * supaya FE cukup membaca satu struktur.
 */
class MukProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAsesi = $this->resource instanceof Asesi;

        return [
            'id' => $this->id,
            'role' => EnsureMukRole::peranDari($this->resource),
            'name' => $isAsesi ? $this->name : $this->nama,
            'email' => $this->email,
            'phone' => $isAsesi ? $this->phone : $this->no_telepon,
            'avatar_url' => $isAsesi
                ? $this->avatar
                : ($this->photo ? asset($this->photo) : null),
            'nomor_registrasi' => $this->when(! $isAsesi, fn () => $this->nomor_registrasi),
        ];
    }
}
