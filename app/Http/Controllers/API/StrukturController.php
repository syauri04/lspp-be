<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\DivisionResource;
use App\Models\Division;
use Illuminate\Http\Request;

class StrukturController extends Controller
{
    public function index()
    {
        $divisions = Division::query()
            ->with('members')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => DivisionResource::collection($divisions)
        ], 200);
    }
}
