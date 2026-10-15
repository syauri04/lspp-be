<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** §2 Rencana Asesmen per unit pada template MAPA.01. */
class MapaTemplateRencana extends Model
{
    /** Kolom "Metode dan Perangkat Asesmen" (sesuai urutan di formulir). */
    public const KOLOM_METODE = [
        'observasi_langsung',
        'kegiatan_terstruktur',
        'tanya_jawab',
        'verifikasi_portofolio',
        'reviu_produk',
        'verifikasi_pihak_ketiga',
    ];

    protected $fillable = [
        'mapa_template_id',
        'unit_kompetensi_id',
        'bukti',
        'jenis_l',
        'jenis_tl',
        'jenis_t',
        'observasi_langsung',
        'kegiatan_terstruktur',
        'tanya_jawab',
        'verifikasi_portofolio',
        'reviu_produk',
        'verifikasi_pihak_ketiga',
    ];

    protected $casts = [
        'jenis_l' => 'boolean',
        'jenis_tl' => 'boolean',
        'jenis_t' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(MapaTemplate::class, 'mapa_template_id');
    }

    public function unitKompetensi(): BelongsTo
    {
        return $this->belongsTo(UnitKompetensi::class);
    }
}
