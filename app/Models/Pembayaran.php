<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    protected $fillable = [
        'kode_transaksi',
        'pendaftaran_sertifikasi_id',
        'jumlah',
        'metode_pembayaran',
        'status',
        'midtrans_transaction_id',
        'midtrans_payload',
        'paid_at',
        'expired_at',
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
        'midtrans_payload' => 'array',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function pendaftaranSertifikasi(): BelongsTo
    {
        return $this->belongsTo(PendaftaranSertifikasi::class, 'pendaftaran_sertifikasi_id');
    }
}
