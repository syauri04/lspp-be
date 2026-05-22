<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\AboutDetailResource;
use App\Http\Resources\AboutHomeResource;
use App\Models\AboutPage;
use Illuminate\Http\Request;

class AboutPageController extends Controller
{
    public function home()
    {
        $about = AboutPage::first();

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => new AboutHomeResource($about)
        ], 200);
    }

    /**
     * Detail Tentang Kami
     */
    public function detail()
    {
        $about = AboutPage::first();

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => new AboutDetailResource($about)
        ], 200);
    }
}
