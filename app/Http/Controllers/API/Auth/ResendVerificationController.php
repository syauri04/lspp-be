<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Models\Asesi;
use Illuminate\Http\Request;

class ResendVerificationController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $asesi = Asesi::where('email', $request->email)->first();

        if (! $asesi) {
            return response()->json(['message' => 'Jika email terdaftar, link verifikasi telah dikirim.']);
        }

        if ($asesi->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah terverifikasi.']);
        }

        $asesi->sendEmailVerificationNotification();

        return response()->json(['message' => 'Jika email terdaftar, link verifikasi telah dikirim.']);
    }
}
