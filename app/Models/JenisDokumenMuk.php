<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisDokumenMuk extends Model
{
    public const APL_01 = 'FR.APL.01';

    public const APL_02 = 'FR.APL.02';

    public const MAPA_01 = 'FR.MAPA.01';

    public const MAPA_02 = 'FR.MAPA.02';

    public const APL = [self::APL_01, self::APL_02];

    protected $fillable = ['kode', 'nama', 'kategori', 'wajib_semua_metode', 'urutan_default'];

    protected $casts = [
        'wajib_semua_metode' => 'boolean',
    ];

    /** Slug URL untuk FE: "FR.APL.01" -> "apl-01", "FR.IA.04A" -> "ia-04a". */
    public function getSlugAttribute(): string
    {
        return str($this->kode)->after('FR.')->lower()->replace('.', '-')->toString();
    }

    /** Jenis dokumen turunan untuk metode tsb, berurutan (tabel jenis_dokumen_metode). */
    public function scopeUntukMetode(Builder $query, string $metode): Builder
    {
        return $query
            ->join('jenis_dokumen_metode as jdm', 'jdm.jenis_dokumen_muk_id', '=', 'jenis_dokumen_muks.id')
            ->where('jdm.metode', $metode)
            ->orderBy('jdm.urutan')
            ->select('jenis_dokumen_muks.*');
    }

    public function dokumenPenilaian(): HasMany
    {
        return $this->hasMany(DokumenPenilaian::class);
    }
}
