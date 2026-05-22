<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsArticle extends Model
{
    protected $fillable = [
        'news_category_id',
        'title',
        'slug',
        'summary',
        'content',
        'image',
        'source',
        'is_view',
        'is_active',
    ];

    protected $casts = [
        'title' => 'array',
        'summary' => 'array',
        'content' => 'array',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(
            NewsCategory::class,
            'news_category_id'
        );
    }
}
