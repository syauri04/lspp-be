<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Calendar extends Model
{
    protected $table = 'calendars';

    protected $fillable = [
        'date',
        'title',
        'link',
        'is_active',
    ];

    protected $casts = [
        'date' => 'date',
        'title' => 'array',
        'is_active' => 'boolean',
    ];
}
