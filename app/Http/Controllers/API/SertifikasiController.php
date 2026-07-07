<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategorySkemaResource;
use App\Http\Resources\SkemaSertifikasiCardResource;
use App\Http\Resources\SkemaSertifikasiDetailResource;
use App\Models\CategorySkemaSertifikasi;
use App\Models\SkemaSertifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

    public function index(Request $request)
    {
        $data = SkemaSertifikasi::query()
            ->where('is_active', true)
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->whereRaw('LOWER(title) LIKE ?', ['%' . strtolower($request->search) . '%']);
            })
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->where('category_skema_id', $request->category);
            })
            ->when($request->filled('sort'), function ($query) use ($request) {
                switch ($request->sort) {
                    case 'terlama':
                        $query->orderBy('created_at', 'asc');
                        break;
                    case 'terbanyak':
                        $query->orderByDesc('is_view');
                        break;
                    case 'terbaru':
                    default:
                        $query->orderByDesc('created_at');
                        break;
                }
            }, function ($query) {
                // default sort kalau tidak ada param sort
                $query->orderByDesc('created_at');
            })
            ->paginate($request->input('limit', 9));

        return SkemaSertifikasiCardResource::collection($data);
    }

    public function detail(string $slug)
    {
        $data = SkemaSertifikasi::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $cacheKey = 'sertifikasi_view_' . $data->id . '_' . request()->ip();

        if (!Cache::has($cacheKey)) {
            $data->increment('is_view');
            $data->refresh();

            Cache::put($cacheKey, true, now()->addHour());
        }

        return new SkemaSertifikasiDetailResource($data);
    }
}
