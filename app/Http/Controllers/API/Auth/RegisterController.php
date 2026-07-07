<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterAsesiRequest;
use App\Models\Asesi;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function __invoke(RegisterAsesiRequest $request)
    {
        $asesi = Asesi::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $asesi->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Registrasi berhasil. Silakan cek email Anda untuk verifikasi sebelum login.',
            'data' => [
                'asesi' => $asesi->only('id', 'name', 'email'),
            ],
        ], 201);
    }
}
