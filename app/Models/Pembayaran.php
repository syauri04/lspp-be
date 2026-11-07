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
        'jumlah_awal',
        'potongan',
        'voucher_id',
        'voucher_kode',
        'metode_pembayaran',
        'status',
        // Kolom di-rename dari midtrans_* (migrasi 2026_08_05) — nama lama membuat nilainya diam-diam dibuang.
        'external_transaction_id',
        'gateway_payload',
        'sesi_doku_lama',
        'paid_at',
        'expired_at',
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
        'jumlah_awal' => 'decimal:2',
        'potongan' => 'decimal:2',
        'gateway_payload' => 'array',
        'sesi_doku_lama' => 'array',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function pendaftaranSertifikasi(): BelongsTo
    {
        return $this->belongsTo(PendaftaranSertifikasi::class, 'pendaftaran_sertifikasi_id');
    }

    /** Bukti transfer manual (terbaru dulu). */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class)->withTrashed();
    }

    /** Pemakaian voucher yang sedang terpasang pada tagihan ini (maks. satu). */
    public function voucherPemakaian(): HasOne
    {
        return $this->hasOne(VoucherPemakaian::class)->where('aktif', true);
    }

    /** Harga sebelum potongan voucher (tagihan lama: sama dengan jumlah). */
    public function hargaAwal(): int
    {
        return (int) round((float) ($this->jumlah_awal ?? $this->jumlah));
    }

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
