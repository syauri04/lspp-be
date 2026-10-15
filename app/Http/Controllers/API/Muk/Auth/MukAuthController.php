<?php

namespace App\Http\Controllers\API\Muk\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Muk\MukLoginRequest;
use App\Http\Resources\Muk\MukProfileResource;
use App\Services\Muk\MukAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function me(Request $request): MukProfileResource
    {
        return new MukProfileResource($request->user());
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
