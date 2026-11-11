<?php

namespace App\Services\Muk;

use App\Models\PenilaianMuk;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Super admin CMS membuka MUK (baca & cetak dokumen) tanpa password terpisah:
 *  1. CMS (sesi super admin) membuat KODE acak sekali pakai, berlaku singkat, terikat ke user + penilaian;
 *     yang disimpan di cache hanya hash-nya.
 *  2. MUK menukar kode itu (sekali) menjadi token Sanctum berability ["muk:admin", "muk:penilaian:{id}"]
 *     yang berumur pendek. Token hanya dapat membuka penilaian itu, hanya jalur baca (lihat routes/api.php).
 * Hak super admin dicek ulang saat penukaran dan pada setiap request (EnsureMukRole).
 */
class AksesAdminMuk
{
    public const PERAN = 'super-admin';

    private const PREFIX_CACHE = 'muk-admin-kode:';

    private const PREFIX_ABILITY = 'muk:penilaian:';

    public function buatKode(User $user, PenilaianMuk $penilaian): string
    {
        $kode = Str::random(64);
        Cache::put(self::PREFIX_CACHE.hash('sha256', $kode), [
            'user_id' => $user->id,
            'penilaian_id' => $penilaian->id,
        ], now()->addSeconds((int) config('muk.admin_kode_ttl_seconds')));

        return $kode;
    }

    /** @return array{0: NewAccessToken, 1: int} token & id penilaian */
    public function tukar(string $kode): array
    {
        // pull = ambil sekaligus hapus: kode tidak dapat dipakai dua kali.
        $data = Cache::pull(self::PREFIX_CACHE.hash('sha256', $kode));
        $user = $data ? User::find($data['user_id']) : null;
        $penilaian = $data ? PenilaianMuk::find($data['penilaian_id']) : null;

        if (! $user || ! $penilaian || ! $user->hasRole(self::PERAN)) {
            throw ValidationException::withMessages(['kode' => 'Tautan akses tidak valid atau sudah kedaluwarsa. Buka kembali dari CMS.']);
        }

        $token = $user->createToken(
            config('muk.token_name').'-admin',
            ['muk:admin', self::PREFIX_ABILITY.$penilaian->id],
            now()->addMinutes((int) config('muk.admin_token_ttl_minutes')),
        );

        return [$token, $penilaian->id];
    }

    /** @return int[] penilaian yang boleh dibuka token admin ini (kosong bila bukan token admin MUK). */
    public static function penilaianDariToken(User $user): array
    {
        $token = $user->currentAccessToken();
        if (! $token instanceof PersonalAccessToken || ! in_array('muk:admin', $token->abilities ?? [], true)) {
            return [];
        }

        return collect($token->abilities)
            ->filter(fn ($a) => is_string($a) && str_starts_with($a, self::PREFIX_ABILITY))
            ->map(fn ($a) => (int) substr($a, strlen(self::PREFIX_ABILITY)))
            ->filter()
            ->values()
            ->all();
    }
}
