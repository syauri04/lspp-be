<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuktiPersyaratanDasar extends Model
{
    protected $fillable = ['skema_sertifikasi_id', 'deskripsi', 'urutan'];

    public function skemaSertifikasi(): BelongsTo
    {
        return $this->belongsTo(SkemaSertifikasi::class);
    }

    public function apl01Verifikasi(): HasMany
    {
        return $this->hasMany(Apl01VerifikasiPersyaratan::class);
    }
}
