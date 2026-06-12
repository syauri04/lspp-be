<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\CategorySkemaSertifikasi;
use App\Models\SkemaSertifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SkemaSertifikasiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = SkemaSertifikasi::with('category');
        $sertifikasis = $query
            ->orderBy('created_at')
            ->get();
        return view('cms.skema-sertifikasi.index', compact('sertifikasis'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('cms.skema-sertifikasi.form', [
            'sertifikasis' => new SkemaSertifikasi(),
            'action' => route('skema-sertifikasi.store'),
            'categories' => CategorySkemaSertifikasi::orderBy('created_at')->get(),
            'method' => 'POST',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_skema_id' => 'required',
            'title_id' => 'required',
            'title_en' => 'required',
            'summary_id' => 'nullable',
            'summary_en' => 'nullable',
            'description_id' => 'nullable',
            'description_en' => 'nullable',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:3048',
            'amount' => 'required|numeric|min:0',
        ]);

        $slug = Str::slug($request->title_id);
        $count = SkemaSertifikasi::where('slug', 'like', "{$slug}%")->count();
        $slug = $count ? "{$slug}-" . ($count + 1) : $slug;

        $image = null;

        if ($request->hasFile('image')) {

            $file = $request->file('image');

            $filename = time() . '.' .
                $file->getClientOriginalExtension();

            $file->move(
                public_path('uploads/skema-sertifikasi'),
                $filename
            );

            $image = 'uploads/skema-sertifikasi/' . $filename;
        }

        SkemaSertifikasi::create([
            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],
            'slug' => $slug,
            'summary' => [
                'id' => $request->summary_id,
                'en' => $request->summary_en,
            ],
            'description' => [
                'id' => $request->description_id,
                'en' => $request->description_en,
            ],
            'amount' => $request->amount,
            'is_active' => $request->is_active ?? false,
            'category_skema_id' => $request->category_skema_id,
            'image' => $image,
        ]);

        return redirect()
            ->route('skema-sertifikasi.index')
            ->with('success', 'Skema Sertifikasi created');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = SkemaSertifikasi::findOrFail($id);
        return view('cms.skema-sertifikasi.form', [
            'data' => $data,
            'action' => route('skema-sertifikasi.update', $id),
            'categories' => CategorySkemaSertifikasi::orderBy('created_at')->get(),
            'method' => 'PUT', // Laravel method spoofing
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SkemaSertifikasi $skema_sertifikasi)
    {
        $request->validate([
            'category_skema_id' => 'required',
            'title_id' => 'required',
            'title_en' => 'required',
            'summary_id' => 'nullable',
            'summary_en' => 'nullable',
            'description_id' => 'nullable',
            'description_en' => 'nullable',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:3048',
            'amount' => 'required|numeric|min:0',
        ]);

        $imagePath = $skema_sertifikasi->image;

        if ($request->hasFile('image')) {

            if (
                $skema_sertifikasi->image &&
                file_exists(public_path($skema_sertifikasi->image))
            ) {
                unlink(public_path($skema_sertifikasi->image));
            }

            $image = $request->file('image');

            $filename = time() . '.' .
                $image->getClientOriginalExtension();

            $image->move(
                public_path('uploads/skema-sertifikasi'),
                $filename
            );

            $imagePath =
                'uploads/skema-sertifikasi/' . $filename;
        }

        if ($skema_sertifikasi->title['id'] !== $request->title_id) {
            $slug = Str::slug($request->title_id);

            $count = SkemaSertifikasi::where('slug', 'like', "{$slug}%")
                ->where('id', '!=', $skema_sertifikasi->id)
                ->count();

            $slug = $count ? "{$slug}-" . ($count + 1) : $slug;
        } else {
            $slug = $skema_sertifikasi->slug;
        }

        $skema_sertifikasi->update([

            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],
            'slug' => $slug,
            'summary' => [
                'id' => $request->summary_id,
                'en' => $request->summary_en,
            ],
            'description' => [
                'id' => $request->description_id,
                'en' => $request->description_en,
            ],
            'amount' => $request->amount,
            'category_skema_id' => $request->category_skema_id,
            'image' => $imagePath,
            'is_active' => $request->is_active,
        ]);

        return redirect()
            ->route('skema-sertifikasi.index')
            ->with(
                'success',
                'Skema Sertifikasi updated successfully'
            );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SkemaSertifikasi $skema_sertifikasi)
    {
        if (
            $skema_sertifikasi->image &&
            file_exists(public_path($skema_sertifikasi->image))
        ) {

            unlink(public_path($skema_sertifikasi->image));
        }

        $skema_sertifikasi->delete();

        return redirect()
            ->route('skema-sertifikasi.index')
            ->with(
                'success',
                'Sekema Sertifikasi deleted successfully'
            );
    }
}
