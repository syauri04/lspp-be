<?php

namespace App\Http\Middleware;

use App\Models\Asesi;
use App\Models\Asesor;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pakai: ->middleware('muk.role:asesi'), 'muk.role:asesor', atau 'muk.role:asesi,asesor'.
 *
 * Syarat lolos:
 * - model user sesuai peran (instanceof Asesi / Asesor),
 * - token punya ability "muk:{peran}" secara EKSPLISIT. Token lspp-fe berability ['*']
 *   sengaja ditolak (tidak pakai tokenCan(), karena '*' akan lolos), sehingga login MUK
 *   terpisah dari login website utama,
 * - asesor masih berstatus aktif (dicek tiap request, bukan hanya saat login).
 */
class EnsureMukRole
{
    public function handle(Request $request, Closure $next, string ...$peranDiizinkan): Response
    {
        $user = $request->user();
        $peran = self::peranDari($user);
        $token = $user?->currentAccessToken();

        if (
            $peran === null
            || ! in_array($peran, $peranDiizinkan, true)
            || ! $token instanceof PersonalAccessToken
            || ! in_array("muk:{$peran}", $token->abilities ?? [], true)
        ) {
            abort(403, 'Akses ditolak untuk peran ini.');
        }

        if ($user instanceof Asesor && $user->status !== 'aktif') {
            $user->tokens()->delete();
            abort(401, 'Akun asesor tidak aktif.');
        }

        return $next($request);
    }

    public static function peranDari(mixed $user): ?string
    {
        return match (true) {
            $user instanceof Asesi => 'asesi',
            $user instanceof Asesor => 'asesor',
            default => null,
        };
    }
}
