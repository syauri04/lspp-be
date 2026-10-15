<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Template FR.MAPA.01/02 per skema + metode (diisi admin di CMS). */
class MapaTemplate extends Model
{
    public const BAGIAN = ['konfirmasi', 'penyusun_mapa01', 'penyusun_mapa02'];

    /** Baris tetap "Konfirmasi dengan orang yang relevan" (sesuai format MAPA.01). */
    public const PERAN_KONFIRMASI = [
        'Manajer sertifikasi LSP',
        'Master Asesor / Master Trainer / Lead Asesor Kompetensi',
        'Manajer pelatihan Lembaga Training terakreditasi / Lembaga Training terdaftar',
        'Manajer atau supervisor ditempat kerja',
    ];

    protected $fillable = ['skema_sertifikasi_id', 'metode'];

    public function skema(): BelongsTo
    {
        return $this->belongsTo(SkemaSertifikasi::class, 'skema_sertifikasi_id');
    }

    public function rencana(): HasMany
    {
        return $this->hasMany(MapaTemplateRencana::class);
    }

    public function penandatangan(): HasMany
    {
        return $this->hasMany(MapaTemplatePenandatangan::class)->orderBy('urutan')->orderBy('id');
    }
}
