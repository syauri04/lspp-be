<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jejak audit proses penerbitan sertifikat (hanya ditambah, tidak diubah). */
class RiwayatPenerbitanSertifikasi extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['penerbitan_sertifikasi_id', 'aksi', 'keterangan', 'meta', 'user_id'];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
