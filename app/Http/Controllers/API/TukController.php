<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\TukResource;
use App\Models\TUK;
use Illuminate\Http\Request;

class TukController extends Controller
{
    public function index()
    {
        $tuks = TUK::query()
            ->where('is_active', 1)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => TukResource::collection($tuks)
        ], 200);
    }
}
