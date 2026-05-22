<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = [
        'division_id',
        'name',
        'position',
        'photo',
        'linkedin_url',
        'instagram_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'position' => 'array',
        'is_active' => 'boolean',
    ];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }
}
