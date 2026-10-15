<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penandatangan template MAPA (konfirmasi orang relevan, penyusun & validator). */
class MapaTemplatePenandatangan extends Model
{
    protected $fillable = [
        'mapa_template_id',
        'bagian',
        'peran',
        'nama',
        'no_reg',
        'path_ttd',
        'tanggal',
        'urutan',
    ];

    protected $hidden = ['path_ttd'];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(MapaTemplate::class, 'mapa_template_id');
    }
}
