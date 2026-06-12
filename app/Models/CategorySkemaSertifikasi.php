<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorySkemaSertifikasi extends Model
{
    protected $table = 'category_skema_sertifikasi';

    protected $fillable = [
        'kategori',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'kategori' => 'array',
        'is_active' => 'boolean',
    ];

    public function skemaSertifikasi(): HasMany
    {
        return $this->hasMany(
            SkemaSertifikasi::class,
            'category_skema_id'
        );
    }
}
