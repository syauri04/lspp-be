<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Models\Asesi;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/auth/email/verify?id=&hash=&expires=&signature=  (middleware 'signed' + throttle)
 *
 * Hanya memverifikasi email, TIDAK login otomatis: link di email bisa diteruskan, tersimpan di
 * riwayat browser, atau dibuka pemindai link kantor — tidak boleh menjadi "kunci masuk" akun.
 * Asesi login sendiri setelahnya. Idempoten: membuka link yang sama berulang tetap sukses.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $asesi = Asesi::find($request->query('id'));

        // Pesan sama untuk id tidak ada / hash salah: tidak membocorkan keberadaan akun.
        if (! $asesi || ! hash_equals(sha1($asesi->getEmailForVerification()), (string) $request->query('hash'))) {
            return response()->json(['message' => 'Link verifikasi tidak valid.'], 403);
        }

        if ($asesi->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email Anda sudah terverifikasi. Silakan login.',
                'data' => ['sudah_terverifikasi' => true, 'email' => $asesi->email],
            ]);
        }

        if ($asesi->markEmailAsVerified()) {
            event(new Verified($asesi));
        }

        return response()->json([
            'message' => 'Email berhasil diverifikasi. Silakan login untuk melanjutkan.',
            'data' => ['sudah_terverifikasi' => false, 'email' => $asesi->email],
        ]);
    }
}
