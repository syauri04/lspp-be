<?php

namespace App\Http\Controllers\API\Muk\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Muk\MukLoginRequest;
use App\Http\Requests\Muk\UbahPasswordAsesorRequest;
use App\Http\Resources\Muk\MukProfileResource;
use App\Services\Muk\AksesAdminMuk;
use App\Services\Muk\MukAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

class MukAuthController extends Controller
{
    public function __construct(private MukAuthService $auth) {}

    public function loginAsesi(MukLoginRequest $request): JsonResponse
    {
        return $this->responsToken(
            $this->auth->loginAsesi($request->validated('email'), $request->validated('password'), $request->ip())
        );
    }

    public function loginAsesor(MukLoginRequest $request): JsonResponse
    {
        return $this->responsToken(
            $this->auth->loginAsesor($request->validated('email'), $request->validated('password'), $request->ip())
        );
    }

    /** Tukar kode sekali pakai dari CMS menjadi token admin MUK baca-saja untuk satu penilaian. */
    public function tukarAdmin(Request $request, AksesAdminMuk $akses): JsonResponse
    {
        $validated = $request->validate(['kode' => ['required', 'string', 'size:64', 'alpha_num']]);
        [$token, $penilaianId] = $akses->tukar($validated['kode']);

        $respons = $this->responsToken($token);
        $isi = $respons->getData(true);
        $isi['data']['penilaian_id'] = $penilaianId;

        return $respons->setData($isi);
    }

    public function me(Request $request): MukProfileResource
    {
        return new MukProfileResource($request->user());
    }

    /**
     * Asesor mengganti password sendiri. Wajib password saat ini; setelah berhasil semua sesi lain
     * (token MUK di perangkat lain) dicabut, sesi yang sedang dipakai tetap aktif.
     */
    public function ubahPassword(UbahPasswordAsesorRequest $request): JsonResponse
    {
        $asesor = $request->user();
        if (! Hash::check($request->validated('current_password'), (string) $asesor->password)) {
            throw ValidationException::withMessages(['current_password' => 'Password saat ini salah.']);
        }

        DB::transaction(function () use ($asesor, $request) {
            $asesor->forceFill(['password' => $request->validated('password')])->save(); // di-hash oleh cast
            $asesor->tokens()->whereKeyNot($request->user()->currentAccessToken()->getKey())->delete();
        });

        return response()->json(['message' => 'Password berhasil diubah. Sesi di perangkat lain telah dikeluarkan.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    private function responsToken(NewAccessToken $token): JsonResponse
    {
        $user = $token->accessToken->tokenable;

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
                'user' => new MukProfileResource($user),
            ],
        ]);
    }
}
