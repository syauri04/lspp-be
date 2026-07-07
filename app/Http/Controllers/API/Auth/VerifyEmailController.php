<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Models\Asesi;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request)
    {
        $asesi = Asesi::findOrFail($request->query('id'));

        if (! hash_equals(
            sha1($asesi->getEmailForVerification()),
            (string) $request->query('hash')
        )) {
            return response()->json(['message' => 'Link verifikasi tidak valid.'], 403);
        }

        if ($asesi->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah terverifikasi sebelumnya.']);
        }

        $asesi->markEmailAsVerified();

        $token = $asesi->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Email berhasil diverifikasi.',
            'data' => [
                'asesi' => $asesi,
                'token' => $token,
            ],
        ]);
    }
}
