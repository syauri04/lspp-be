<?php

namespace App\Http\Middleware;

use App\Models\Asesi;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Untuk endpoint akun asesi milik lspp-fe (/api/auth/me, profile, password, logout).
 *
 * Guard sanctum menerima token dari model apa pun (provider null), jadi tanpa
 * middleware ini token asesor (atau token khusus MUK) bisa memanggil endpoint asesi.
 * Token lspp-fe dibuat dengan ability default ['*']; token MUK hanya berability
 * "muk:*" dan ditolak di sini.
 */
class EnsureAsesiWebsite
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (
            ! $user instanceof Asesi
            || ! $token instanceof PersonalAccessToken
            || ! in_array('*', $token->abilities ?? [], true)
        ) {
            abort(403, 'Endpoint ini hanya untuk akun asesi.');
        }

        return $next($request);
    }
}
