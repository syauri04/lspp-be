<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElemenKompetensi extends Model
{
    protected $fillable = ['unit_kompetensi_id', 'nomor_elemen', 'nama_elemen', 'urutan'];

    public function unitKompetensi(): BelongsTo
    {
        return $this->belongsTo(UnitKompetensi::class);
    }

    public function kriteriaUnjukKerja(): HasMany
    {
        return $this->hasMany(KriteriaUnjukKerja::class)->orderBy('urutan');
    }

    public function apl02Jawaban(): HasMany
    {
        return $this->hasMany(Apl02Jawaban::class);
    }
}
