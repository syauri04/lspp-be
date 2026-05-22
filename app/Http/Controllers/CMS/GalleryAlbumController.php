<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class GalleryAlbumController extends Controller
{
    public function index()
    {
        $albums = GalleryAlbum::withCount('photos')
            ->latest()
            ->get();

        return view(
            'cms.gallery.index',
            compact('albums')
        );
    }

    public function create()
    {
        return view('cms.gallery.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title_id' => 'required',
            'title_en' => 'required',
            'cover_image' => 'required|image',
            'photos.*' => 'nullable|image',
        ]);

        // COVER IMAGE
        $coverImage = null;

        if ($request->hasFile('cover_image')) {

            $coverImage = $request->file('cover_image')
                ->store(
                    'uploads/gallery/covers',
                    'public'
                );
        }

        // CREATE ALBUM
        $album = GalleryAlbum::create([

            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],

            'slug' => Str::slug(
                $request->title_en
            ) . '-' . time(),

            'cover_image' => $coverImage,

            'event_date' => $request->event_date,

            'is_active' => $request->has('is_active'),
        ]);

        // MULTIPLE PHOTOS
        if ($request->hasFile('photos')) {

            foreach ($request->file('photos') as $index => $photo) {

                $filename =
                    time() . '_' . $index . '.webp';

                $thumbnailName =
                    'thumb_' . $filename;

                $imagePath =
                    storage_path(
                        'app/public/uploads/gallery/photos/'
                    );

                $thumbnailPath =
                    storage_path(
                        'app/public/uploads/gallery/thumbnails/'
                    );

                if (!file_exists($imagePath)) {
                    mkdir($imagePath, 0777, true);
                }

                if (!file_exists($thumbnailPath)) {
                    mkdir($thumbnailPath, 0777, true);
                }

                // COMPRESS IMAGE
                Image::read($photo)
                    ->scale(width: 1600)
                    ->toWebp(75)
                    ->save(
                        $imagePath . '/' . $filename
                    );

                // THUMBNAIL
                Image::read($photo)
                    ->cover(500, 350)
                    ->toWebp(60)
                    ->save(
                        $thumbnailPath . '/' . $thumbnailName
                    );

                GalleryPhoto::create([

                    'gallery_album_id' => $album->id,

                    'image' =>
                    'uploads/gallery/photos/' .
                        $filename,

                    'thumbnail' =>
                    'uploads/gallery/thumbnails/' .
                        $thumbnailName,

                    'sort_order' => $index,

                    'is_active' => true,
                ]);
            }
        }

        return redirect()
            ->route('gallery-albums.index')
            ->with(
                'success',
                'Gallery album created'
            );
    }

    public function edit(GalleryAlbum $galleryAlbum)
    {
        $galleryAlbum->load('photos');

        return view(
            'cms.gallery.edit',
            compact('galleryAlbum')
        );
    }

    public function update(
        Request $request,
        GalleryAlbum $galleryAlbum
    ) {

        $coverImage = $galleryAlbum->cover_image;

        if ($request->hasFile('cover_image')) {

            $coverImage = $request->file('cover_image')
                ->store(
                    'uploads/gallery/covers',
                    'public'
                );
        }

        $galleryAlbum->update([

            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],

            'cover_image' => $coverImage,

            'event_date' => $request->event_date,

            'is_active' => $request->has('is_active'),
        ]);

        // ADD NEW PHOTOS
        if ($request->hasFile('photos')) {

            foreach ($request->file('photos') as $index => $photo) {

                $filename =
                    time() . '_' . $index . '.webp';

                $thumbnailName =
                    'thumb_' . $filename;

                $imagePath =
                    storage_path(
                        'app/public/uploads/gallery/photos/'
                    );

                $thumbnailPath =
                    storage_path(
                        'app/public/uploads/gallery/thumbnails/'
                    );

                Image::read($photo)
                    ->scale(width: 1600)
                    ->toWebp(75)
                    ->save(
                        $imagePath . '/' . $filename
                    );

                Image::read($photo)
                    ->cover(500, 350)
                    ->toWebp(60)
                    ->save(
                        $thumbnailPath . '/' . $thumbnailName
                    );

                GalleryPhoto::create([

                    'gallery_album_id' => $galleryAlbum->id,

                    'image' =>
                    'uploads/gallery/photos/' .
                        $filename,

                    'thumbnail' =>
                    'uploads/gallery/thumbnails/' .
                        $thumbnailName,

                    'sort_order' => $index,

                    'is_active' => true,
                ]);
            }
        }

        return redirect()
            ->route('gallery-albums.index')
            ->with(
                'success',
                'Gallery updated'
            );
    }

    public function destroy(GalleryAlbum $galleryAlbum)
    {
        $galleryAlbum->delete();

        return redirect()
            ->route('gallery-albums.index')
            ->with(
                'success',
                'Gallery deleted'
            );
    }
}
