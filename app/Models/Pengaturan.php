<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan aplikasi key-value yang bisa diubah admin dari CMS tanpa deploy.
 * Dibaca lewat cache (dihapus otomatis saat nilai disimpan).
 */
class Pengaturan extends Model
{
    /** Saklar metode pembayaran (lihat PengaturanPembayaranController). */
    public const PEMBAYARAN_DOKU_AKTIF = 'pembayaran.doku_aktif';

    public const PEMBAYARAN_MANUAL_AKTIF = 'pembayaran.manual_aktif';

    /** Nilai bawaan bila belum pernah diatur di CMS. */
    private const BAWAAN = [
        self::PEMBAYARAN_DOKU_AKTIF => true,
        self::PEMBAYARAN_MANUAL_AKTIF => true,
    ];

    protected $fillable = ['kunci', 'nilai'];

    protected $casts = ['nilai' => 'array'];

    public static function ambil(string $kunci, mixed $bawaan = null): mixed
    {
        $nilai = Cache::rememberForever(self::kunciCache($kunci), fn () => [
            'ada' => ($baris = static::where('kunci', $kunci)->first()) !== null,
            'nilai' => $baris?->nilai['v'] ?? null,
        ]);

        return $nilai['ada'] ? $nilai['nilai'] : ($bawaan ?? self::BAWAAN[$kunci] ?? null);
    }

    public static function simpan(string $kunci, mixed $nilai): void
    {
        static::updateOrCreate(['kunci' => $kunci], ['nilai' => ['v' => $nilai]]);
        Cache::forget(self::kunciCache($kunci));
    }

    public static function aktif(string $kunci): bool
    {
        return (bool) static::ambil($kunci);
    }

    private static function kunciCache(string $kunci): string
    {
        return 'pengaturan:'.$kunci;
    }
}
