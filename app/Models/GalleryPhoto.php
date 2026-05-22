<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GalleryPhoto extends Model
{
    protected $fillable = [
        'gallery_album_id',
        'image',
        'caption',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'caption' => 'array',
        'is_active' => 'boolean',
    ];

    public function album()
    {
        return $this->belongsTo(GalleryAlbum::class);
    }
}
