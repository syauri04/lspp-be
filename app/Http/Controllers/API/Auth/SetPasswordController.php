<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetPasswordRequest;
use Illuminate\Support\Facades\Hash;

class SetPasswordController extends Controller
{
    public function __invoke(SetPasswordRequest $request)
    {
        $asesi = $request->user();

        if ($asesi->password) {
            return response()->json([
                'message' => 'Anda sudah memiliki password. Gunakan fitur ganti password untuk mengubahnya.',
            ], 422);
        }

        $asesi->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password berhasil dibuat. Sekarang Anda juga bisa login secara manual.',
        ]);
    }
}
