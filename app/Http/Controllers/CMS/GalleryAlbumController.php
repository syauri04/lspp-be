<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GalleryAlbumController extends Controller
{
    // =========================================================
    //  PATH CONSTANTS
    //  File disimpan langsung di public/
    //  Akses di blade: asset($photo->image)
    // =========================================================
    private const COVER_DIR  = 'uploads/gallery/covers';
    private const PHOTOS_DIR = 'uploads/gallery/photos';

    public function index()
    {
        $albums = GalleryAlbum::withCount('photos')
            ->latest()
            ->get();

        return view('cms.gallery.index', compact('albums'));
    }

    public function create()
    {
        return view('cms.gallery.form', [
            'galleryAlbum' => new GalleryAlbum(),
            'action'       => route('gallery-albums.store'),
            'method'       => 'POST',
        ]);
    }

    public function store(Request $request)
    {
        // dd([
        //     'all_input_keys'  => array_keys($request->all()),
        //     'all_files_keys'  => array_keys($request->allFiles()),
        //     'has_photos'      => $request->hasFile('photos'),
        //     'has_cover'       => $request->hasFile('cover_image'),
        //     'photos_raw'      => $request->file('photos'),
        //     'title_id'        => $request->title_id,
        // ]);
        $request->validate([
            'title_id'        => 'required|string|max:255',
            'title_en'        => 'required|string|max:255',
            'summary_id'      => 'nullable|string',
            'summary_en'      => 'nullable|string',
            'cover_image'     => 'required|image|max:5120',
            'event_date'      => 'nullable|date',
            'photos.*'        => 'nullable|image|max:5120',
            'captions_id.*'      => 'nullable|string|max:255',
            'captions_en.*'      => 'nullable|string|max:255',
        ]);

        $album = GalleryAlbum::create([
            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],
            'summary' => [
                'id' => $request->summary_id,
                'en' => $request->summary_en,
            ],
            'slug'        => Str::slug($request->title_en) . '-' . time(),
            'cover_image' => $this->saveCover($request),
            'event_date'  => $request->event_date,
            'is_active'   => $request->input('is_active', 0),
        ]);

        $this->savePhotos($request, $album->id);

        return redirect()
            ->route('gallery-albums.index')
            ->with('success', 'Gallery album berhasil dibuat.');
    }

    public function show(GalleryAlbum $galleryAlbum)
    {
        $galleryAlbum->load(['photos' => fn($q) => $q->orderBy('sort_order')]);

        return view('cms.gallery.show', compact('galleryAlbum'));
    }

    public function edit(GalleryAlbum $galleryAlbum)
    {
        $galleryAlbum->load(['photos' => fn($q) => $q->orderBy('sort_order')]);

        return view('cms.gallery.form', [
            'data'   => $galleryAlbum,
            'action' => route('gallery-albums.update', $galleryAlbum->id),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, GalleryAlbum $galleryAlbum)
    {
        $request->validate([
            'title_id'        => 'required|string|max:255',
            'title_en'        => 'required|string|max:255',
            'summary_id'      => 'nullable|string',
            'summary_en'      => 'nullable|string',
            'cover_image'     => 'nullable|image|max:5120',
            'event_date'      => 'nullable|date',
            'photos.*'        => 'nullable|image|max:5120',
            'captions_id.*'      => 'nullable|string|max:255',
            'captions_en.*'      => 'nullable|string|max:255',
            // caption untuk foto existing
            'existing_captions_id.*' => 'nullable|string|max:255',
            'existing_captions_en.*' => 'nullable|string|max:255',
        ]);

        // Cover — ganti hanya jika ada upload baru
        $coverImage = $galleryAlbum->cover_image;
        if ($request->hasFile('cover_image')) {
            $this->deletePublicFile($coverImage);
            $coverImage = $this->saveCover($request);
        }

        $galleryAlbum->update([
            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],
            'summary' => [
                'id' => $request->summary_id,
                'en' => $request->summary_en,
            ],
            'cover_image' => $coverImage,
            'event_date'  => $request->event_date,
            'is_active'   => $request->input('is_active', 0),
        ]);

        // Update caption foto existing
        // Input: existing_captions[{photo_id}] = "teks caption"
        if ($request->filled('existing_captions_id') || $request->filled('existing_captions_en')) {
            foreach ($request->input('existing_captions_id') as $photoId => $captionId) {
                $captionEn = $request->input('existing_captions_en.' . $photoId);
                GalleryPhoto::where('id', $photoId)
                    ->where('gallery_album_id', $galleryAlbum->id)
                    ->update([
                        'caption' => [
                            'id' => $captionId,
                            'en' => $captionEn,
                        ],
                    ]);
            }
        }

        // Tambah foto baru
        $this->savePhotos($request, $galleryAlbum->id, $galleryAlbum->photos()->count());

        return redirect()
            ->route('gallery-albums.index')
            ->with('success', 'Gallery berhasil diperbarui.');
    }

    public function destroy(GalleryAlbum $galleryAlbum)
    {
        foreach ($galleryAlbum->photos as $photo) {
            $this->deletePublicFile($photo->image);
        }

        $this->deletePublicFile($galleryAlbum->cover_image);

        $galleryAlbum->delete();

        return redirect()
            ->route('gallery-albums.index')
            ->with('success', 'Gallery berhasil dihapus.');
    }

    // =========================================================
    //  GALLERY PHOTO METHODS
    // =========================================================

    /**
     * Hapus satu foto.
     * Route: DELETE /gallery-photos/{galleryPhoto}
     */
    public function destroyPhoto(GalleryPhoto $galleryPhoto)
    {
        $this->deletePublicFile($galleryPhoto->image);

        $albumId = $galleryPhoto->gallery_album_id;
        $galleryPhoto->delete();

        // Re-index sort_order
        GalleryPhoto::where('gallery_album_id', $albumId)
            ->orderBy('sort_order')
            ->get()
            ->each(fn($p, $i) => $p->update(['sort_order' => $i]));

        return back()->with('success', 'Foto berhasil dihapus.');
    }

    /**
     * Update sort_order via AJAX.
     * Route: POST /gallery-photos/reorder
     * Body : { "order": [id1, id2, ...] }
     */
    public function reorderPhotos(Request $request)
    {
        $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:gallery_photos,id',
        ]);

        foreach ($request->order as $index => $photoId) {
            GalleryPhoto::where('id', $photoId)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['message' => 'Urutan berhasil disimpan.']);
    }

    // =========================================================
    //  PRIVATE HELPERS
    // =========================================================

    private function saveCover(Request $request): string
    {
        $file     = $request->file('cover_image');
        $filename = time() . '_cover.' . $file->getClientOriginalExtension();
        $dest     = public_path(self::COVER_DIR);

        if (! file_exists($dest)) mkdir($dest, 0777, true);

        $file->move($dest, $filename);

        return self::COVER_DIR . '/' . $filename;
    }

    /**
     * Simpan foto baru ke public/uploads/gallery/photos/
     *
     * Input dari form:
     *   photos[]      → file foto
     *   captions_id[]    → caption ID per foto (index sejajar dengan photos[])
     *   captions_en[]    → caption EN per foto (index sejajar dengan photos[])
     */
    private function savePhotos(Request $request, int $albumId, int $offset = 0): void
    {
        if (! $request->hasFile('photos')) return;

        $photosDir = public_path(self::PHOTOS_DIR);
        if (! file_exists($photosDir)) mkdir($photosDir, 0777, true);

        $captionsid = $request->input('captions_id', []);
        $captionsen = $request->input('captions_en', []);
        $captions = [
            'id' => $captionsid,
            'en' => $captionsen,
        ];
        foreach ($request->file('photos') as $index => $photo) {
            $filename = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();

            $photo->move($photosDir, $filename);

            GalleryPhoto::create([
                'gallery_album_id' => $albumId,
                'image'            => self::PHOTOS_DIR . '/' . $filename,
                'caption' => [
                    'id' => $captions['id'][$index] ?? null,
                    'en' => $captions['en'][$index] ?? null,
                ],
                'sort_order'       => $offset + $index,
            ]);
        }
    }

    private function deletePublicFile(?string $relativePath): void
    {
        if (! $relativePath) return;

        $fullPath = public_path($relativePath);
        if (file_exists($fullPath)) unlink($fullPath);
    }
}
