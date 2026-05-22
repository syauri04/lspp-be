<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\HeroBannerResource;
use App\Models\HeroBanner;
use Illuminate\Http\Request;

class HeroBannerController extends Controller
{
    public function index()
    {
        $data = HeroBanner::query()
            ->where('is_active', 1)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => HeroBannerResource::collection($data)
        ], 200);
    }
}
