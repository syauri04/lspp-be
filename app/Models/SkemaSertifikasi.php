<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkemaSertifikasi extends Model
{
    protected $table = 'skema_sertifikasi';

    protected $fillable = [
        'category_skema_id',
        'title',
        'slug',
        'summary',
        'description',
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
}
