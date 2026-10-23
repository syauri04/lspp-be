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

    public const AK_01 = 'FR.AK.01';

    public const AK_02 = 'FR.AK.02';

    public const AK_03 = 'FR.AK.03';

    public const AK_04 = 'FR.AK.04';

    public const AK_05 = 'FR.AK.05';

    public const AK_06 = 'FR.AK.06';

    public const AK_07 = 'FR.AK.07';

    public const IA_01 = 'FR.IA.01';

    public const IA_02 = 'FR.IA.02';

    public const IA_03 = 'FR.IA.03';

    public const IA_04A = 'FR.IA.04A';

    public const IA_04B = 'FR.IA.04B';

    public const IA_08 = 'FR.IA.08';

    public const IA_09 = 'FR.IA.09';

    public const IA_10 = 'FR.IA.10';

    public const IA_06A = 'FR.IA.06A';

    public const IA_06B = 'FR.IA.06B';

    public const IA_06C = 'FR.IA.06C';

    public const APL = [self::APL_01, self::APL_02];

    /**
     * Dokumen asesi SETELAH keputusan (FR.AK.02): umpan balik & banding. Tidak menahan penetapan hasil
     * penilaian (kompeten / belum kompeten) dan tetap dapat diisi asesi setelah penilaian selesai.
     */
    public const PASCA_KEPUTUSAN = [self::AK_03, self::AK_04];

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
