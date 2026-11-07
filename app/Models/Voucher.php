<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use SoftDeletes;

    public const NOMINAL = 'nominal';

    public const PERSENTASE = 'persentase';

    public const TIPE_LABEL = [
        self::NOMINAL => 'Nominal (Rp)',
        self::PERSENTASE => 'Persentase (%)',
    ];

    /** Status tagihan yang membuat pemakaian voucher dihitung terpakai (kuota). Tagihan expired/batal tidak. */
    public const STATUS_TAGIHAN_TERPAKAI = ['pending', 'paid'];

    protected $fillable = [
        'kode', 'nama', 'tipe', 'nilai', 'maks_potongan', 'kuota', 'maks_per_asesi',
        'mulai_at', 'berakhir_at', 'aktif', 'dibuat_oleh',
    ];

    protected $casts = [
        'nilai' => 'decimal:2',
        'maks_potongan' => 'decimal:2',
        'kuota' => 'integer',
        'maks_per_asesi' => 'integer',
        'mulai_at' => 'datetime',
        'berakhir_at' => 'datetime',
        'aktif' => 'boolean',
    ];

    /** Kode selalu disimpan & dicari dalam huruf besar tanpa spasi. */
    public static function normalisasiKode(?string $kode): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $kode));
    }

    public function setKodeAttribute(?string $value): void
    {
        $this->attributes['kode'] = self::normalisasiKode($value);
    }

    public function pemakaian(): HasMany
    {
        return $this->hasMany(VoucherPemakaian::class);
    }

    /** Pemakaian yang menghabiskan kuota: masih terpasang & tagihannya pending/lunas. */
    public function pemakaianTerpakai(): HasMany
    {
        return $this->pemakaian()
            ->where('aktif', true)
            ->whereHas('pembayaran', fn (Builder $q) => $q->whereIn('status', self::STATUS_TAGIHAN_TERPAKAI));
    }

    /** Potongan (rupiah bulat) untuk harga tertentu; tidak pernah melebihi harga. */
    public function hitungPotongan(int $harga): int
    {
        $potongan = $this->tipe === self::PERSENTASE
            ? intdiv($harga * (int) round((float) $this->nilai * 100), 10000)
            : (int) floor((float) $this->nilai);

        if ($this->tipe === self::PERSENTASE && $this->maks_potongan !== null) {
            $potongan = min($potongan, (int) floor((float) $this->maks_potongan));
        }

        return max(0, min($potongan, $harga));
    }

    public function labelNilai(): string
    {
        return $this->tipe === self::PERSENTASE
            ? rtrim(rtrim(number_format((float) $this->nilai, 2, ',', '.'), '0'), ',').'%'
                .($this->maks_potongan !== null ? ' (maks. Rp '.number_format((float) $this->maks_potongan, 0, ',', '.').')' : '')
            : 'Rp '.number_format((float) $this->nilai, 0, ',', '.');
    }

    public function statusLabel(): string
    {
        return match (true) {
            ! $this->aktif => 'Nonaktif',
            now()->lt($this->mulai_at) => 'Belum berlaku',
            now()->gt($this->berakhir_at) => 'Kedaluwarsa',
            default => 'Berlaku',
        };
    }
}
