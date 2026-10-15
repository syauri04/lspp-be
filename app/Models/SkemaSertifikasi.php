<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkemaSertifikasi extends Model
{
    protected $table = 'skema_sertifikasi';

    protected $fillable = [
        'category_skema_id',
        'title',
        'slug',
        'jenis_skema',
        'nomor_skema',
        'summary',
        'description',
        'standar_kompetensi_kerja',
        'image',
        'amount',
        'is_view',
        'is_active',
    ];

    protected $casts = [
        'title' => 'array',
        'summary' => 'array',
        'description' => 'array',
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            CategorySkemaSertifikasi::class,
            'category_skema_id'
        );
    }

    public function unitKompetensis(): HasMany
    {
        return $this->hasMany(
            UnitKompetensi::class,
            'skema_sertifikasi_id'
        )->orderBy('urutan');
    }

    public function buktiPersyaratanDasar()
    {
        return $this->hasMany(BuktiPersyaratanDasar::class)->orderBy('urutan');
    }
}
