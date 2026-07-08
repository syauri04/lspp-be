<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoogleLoginRequest;
use App\Models\Asesi;
use Google_Client;
use Illuminate\Support\Str;

class GoogleLoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(GoogleLoginRequest $request)
    {
        $client = new Google_Client(['client_id' => config('services.google.client_id')]);

        $payload = $client->verifyIdToken($request->id_token);

        if (! $payload) {
            return response()->json([
                'message' => 'Token Google tidak valid.',
            ], 401);
        }

        // pastikan email dari Google sudah verified di sisi Google
        if (empty($payload['email_verified'])) {
            return response()->json([
                'message' => 'Email Google belum terverifikasi.',
            ], 422);
        }

        $googleId = $payload['sub'];
        $email = $payload['email'];
        $name = $payload['name'] ?? explode('@', $email)[0];
        $avatar = $payload['picture'] ?? null;

        // cari berdasarkan provider_id dulu (kasus paling akurat)
        $asesi = Asesi::where('provider', 'google')
            ->where('provider_id', $googleId)
            ->first();

        if (! $asesi) {
            // cek apakah email sudah terdaftar lewat cara manual (register biasa)
            $existing = Asesi::where('email', $email)->first();

            if ($existing) {
                // link akun manual yang sudah ada ke Google (gak bikin duplikat)
                $existing->update([
                    'provider' => 'google',
                    'provider_id' => $googleId,
                    'avatar' => $avatar,
                    'email_verified_at' => $existing->email_verified_at ?? now(),
                ]);

                $asesi = $existing;
            } else {
                // buat akun baru, tanpa password (karena login via Google)
                $asesi = Asesi::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => null,
                    'provider' => 'google',
                    'provider_id' => $googleId,
                    'avatar' => $avatar,
                    'email_verified_at' => now(), // otomatis verified karena Google sudah verifikasi
                ]);
            }
        }

        $token = $asesi->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login dengan Google berhasil',
            'data' => [
                'asesi' => $asesi,
                'token' => $token,
            ],
        ]);
    }
}
