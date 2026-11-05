<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** Bukti transfer manual (terbaru dulu). */
    public function buktiPembayaran(): HasMany
    {
        return $this->hasMany(BuktiPembayaran::class)->latest('id');
    }

    /** Bukti transfer yang sedang menunggu verifikasi admin (maksimal satu). */
    public function buktiMenunggu(): HasOne
    {
        return $this->hasOne(BuktiPembayaran::class)->where('status', BuktiPembayaran::MENUNGGU)->latest('id');
    }
}
