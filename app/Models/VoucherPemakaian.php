<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat pemakaian voucher pada satu tagihan (snapshot asesi, skema, potongan). */
class VoucherPemakaian extends Model
{
    protected $fillable = [
        'voucher_id', 'pembayaran_id', 'asesi_id', 'skema_sertifikasi_id', 'kode',
        'jumlah_awal', 'potongan', 'jumlah_akhir', 'aktif', 'dilepas_at',
    ];

    protected $casts = [
        'jumlah_awal' => 'decimal:2',
        'potongan' => 'decimal:2',
        'jumlah_akhir' => 'decimal:2',
        'aktif' => 'boolean',
        'dilepas_at' => 'datetime',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class)->withTrashed();
    }

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class);
    }

    public function asesi(): BelongsTo
    {
        return $this->belongsTo(Asesi::class);
    }

    public function skemaSertifikasi(): BelongsTo
    {
        return $this->belongsTo(SkemaSertifikasi::class);
    }
}
