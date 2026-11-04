<?php

namespace App\Models;

use App\Observers\RiwayatStatusPendaftaranObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Setiap perubahan status memicu notifikasi ke asesi (lihat RiwayatStatusPendaftaranObserver). */
#[ObservedBy(RiwayatStatusPendaftaranObserver::class)]
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
