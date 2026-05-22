<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutPage extends Model
{
    protected $fillable = [
        'title',
        'desc_home',
        'desc_detail',
        'vision',
        'mission',
        'background_image',
        'image_vision',
        'image_mission',
    ];

    protected $casts = [
        'title' => 'array',
        'desc_home' => 'array',
        'desc_detail' => 'array',
        'vision' => 'array',
        'mission' => 'array',
    ];
}
