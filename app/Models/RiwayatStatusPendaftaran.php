<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatStatusPendaftaran extends Model
{
    public $timestamps = false; // hanya pakai created_at (lihat migration: useCurrent())

    protected $fillable = [
        'pendaftaran_sertifikasi_id',
        'status_dari',
        'status_ke',
        'keterangan',
        'changed_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function pendaftaranSertifikasi(): BelongsTo
    {
        return $this->belongsTo(PendaftaranSertifikasi::class, 'pendaftaran_sertifikasi_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
