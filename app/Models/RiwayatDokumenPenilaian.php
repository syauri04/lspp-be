<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RiwayatDokumenPenilaian extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'dokumen_penilaian_id',
        'aksi',
        'status_dari',
        'status_ke',
        'pelaku_type',
        'pelaku_id',
        'catatan',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function dokumenPenilaian(): BelongsTo
    {
        return $this->belongsTo(DokumenPenilaian::class);
    }

    public function pelaku(): MorphTo
    {
        return $this->morphTo();
    }
}
