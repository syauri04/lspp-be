<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GalleryAlbum;
use App\Http\Resources\GalleryAlbumDetailResource;
use App\Http\Resources\GalleryAlbumResource;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function index()
    {
        $albums = GalleryAlbum::query()
            ->where('is_active', true)
            ->withCount([
                'photos' => function ($query) {
                    $query->where('is_active', true);
                }
            ])
            ->orderByDesc('event_date')
            ->get();

        return GalleryAlbumResource::collection($albums);
    }

    public function show(string $slug)
    {
        $album = GalleryAlbum::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'photos' => function ($query) {
                    $query->where('is_active', true)
                        ->orderBy('sort_order');
                }
            ])
            ->firstOrFail();

        return new GalleryAlbumDetailResource($album);
    }
}
