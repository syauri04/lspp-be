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
        // Kolom di-rename dari midtrans_* (migrasi 2026_08_05) — nama lama membuat nilainya diam-diam dibuang.
        'external_transaction_id',
        'gateway_payload',
        'paid_at',
        'expired_at',
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
        'gateway_payload' => 'array',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function pendaftaranSertifikasi(): BelongsTo
    {
        return $this->belongsTo(PendaftaranSertifikasi::class, 'pendaftaran_sertifikasi_id');
    }
}
