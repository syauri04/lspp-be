<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategorySkemaResource;
use App\Http\Resources\SkemaSertifikasiCardResource;
use App\Http\Resources\SkemaSertifikasiDetailResource;
use App\Models\CategorySkemaSertifikasi;
use App\Models\SkemaSertifikasi;
use Illuminate\Http\Request;

class SertifikasiController extends Controller
{
    public function categories()
    {
        $categories = CategorySkemaSertifikasi::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return CategorySkemaResource::collection($categories);
    }

    public function index()
    {
        $data = SkemaSertifikasi::query()
            ->where('is_active', true)
            ->latest()
            ->paginate(9);

        return SkemaSertifikasiCardResource::collection($data);
    }

    public function detail(string $slug)
    {
        $data = SkemaSertifikasi::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $data->increment('is_view');

        $data->refresh();

        return new SkemaSertifikasiDetailResource($data);
    }
}
