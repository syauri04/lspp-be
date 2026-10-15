<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitKompetensi extends Model
{
    protected $table = 'unit_kompetensi';

    protected $fillable = [
        'skema_sertifikasi_id',
        'kode_unit',
        'judul_unit',
        'urutan',
    ];

    public function skemaSertifikasi(): BelongsTo
    {
        return $this->belongsTo(
            SkemaSertifikasi::class,
            'skema_sertifikasi_id'
        );
    }

    public function elemenKompetensi()
    {
        return $this->hasMany(ElemenKompetensi::class)->orderBy('urutan');
    }
}
