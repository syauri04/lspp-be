<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordController extends Controller
{
    public function __invoke(ChangePasswordRequest $request)
    {
        $asesi = $request->user();

        if (! $asesi->password) {
            return response()->json([
                'message' => 'Akun Anda belum memiliki password. Gunakan fitur set password terlebih dahulu.',
            ], 422);
        }

        if (! Hash::check($request->current_password, $asesi->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini salah.'],
            ]);
        }

        $asesi->update([
            'password' => Hash::make($request->password),
        ]);

        // opsional tapi disarankan: revoke semua token lama demi keamanan,
        // paksa re-login di semua device setelah ganti password
        $asesi->tokens()->delete();

        $newToken = $asesi->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Password berhasil diubah. Silakan login ulang di device lain jika diperlukan.',
            'data' => ['token' => $newToken],
        ]);
    }
}
