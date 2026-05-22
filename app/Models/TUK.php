<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TUK extends Model
{
    protected $fillable = [
        'title',
        'city',
        'address',
        'open_days',
        'open_hours',
        'google_maps_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
