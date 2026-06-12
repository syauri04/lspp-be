<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CalendarsResource;
use App\Models\Calendar;
use Illuminate\Http\Request;

class CalendarsController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->integer('month', now()->month);

        $year = $request->integer('year', now()->year);

        $calendars = Calendar::query()
            ->where('is_active', true)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->get();

        return response()->json([
            'month' => $month,
            'year' => $year,
            'data' => CalendarsResource::collection($calendars),
        ]);
    }
}
