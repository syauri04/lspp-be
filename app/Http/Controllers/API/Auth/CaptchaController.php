<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Services\Captcha\CaptchaService;
use Illuminate\Http\JsonResponse;

class CaptchaController extends Controller
{
    public function __invoke(CaptchaService $captcha): JsonResponse
    {
        return response()
            ->json(['data' => $captcha->buat()])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
