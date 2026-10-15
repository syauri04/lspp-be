<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KriteriaUnjukKerja extends Model
{
    protected $fillable = ['elemen_kompetensi_id', 'nomor_kuk', 'deskripsi', 'urutan'];

    public function elemenKompetensi(): BelongsTo
    {
        return $this->belongsTo(ElemenKompetensi::class);
    }
}
