<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = Faq::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return FaqResource::collection($faqs);
    }
}
