<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginAsesiRequest;
use App\Models\Asesi;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __invoke(LoginAsesiRequest $request)
    {
        $asesi = Asesi::where('email', $request->email)->first();

        if (! $asesi || ! $asesi->password || ! Hash::check($request->password, $asesi->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (! $asesi->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Email belum diverifikasi. Silakan cek email Anda.'],
            ]);
        }

        $token = $asesi->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'data' => [
                'asesi' => $asesi,
                'token' => $token,
            ],
        ]);
    }
}
