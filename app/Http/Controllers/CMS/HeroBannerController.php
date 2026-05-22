<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\HeroBanner;
use Illuminate\Http\Request;

class HeroBannerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $heroBanners = HeroBanner::latest()->paginate(10);

        return view('cms.hero-banner.index', compact('heroBanners'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('cms.hero-banner.form', [
            'heroBanner' => new HeroBanner(),
            'action' => route('hero-banner.store'),
            'method' => 'POST',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title_id' => 'required',
            'title_en' => 'required',

            'summary_id' => 'nullable',
            'summary_en' => 'nullable',

            'image' => 'nullable|image',
            'is_active' => 'required|boolean',
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {

            $image = $request->file('image');

            $filename = time() . '.' . $image->getClientOriginalExtension();

            $image->move(
                public_path('uploads/hero-banner'),
                $filename
            );

            $imagePath = 'uploads/hero-banner/' . $filename;
        }

        HeroBanner::create([
            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],

            'summary' => [
                'id' => $request->summary_id,
                'en' => $request->summary_en,
            ],

            'image' => $imagePath,

            'sort_order' => $request->sort_order ?? 0,

            'is_active' => $request->is_active,
        ]);

        return redirect()
            ->route('hero-banner.index')
            ->with('success', 'Hero Banner created successfully');
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
        $data = HeroBanner::findOrFail($id);
        return view('cms.hero-banner.form', [
            'data' => $data,
            'action' => route('hero-banner.update', $id),
            'method' => 'PUT', // Laravel method spoofing
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, HeroBanner $heroBanner)
    {
        $validated = $request->validate([
            'title_id' => 'required',
            'title_en' => 'required',

            'summary_id' => 'nullable',
            'summary_en' => 'nullable',

            'image' => 'nullable|image',

            'sort_order' => 'nullable|integer',
            'is_active' => 'required|boolean',
        ]);

        $imagePath = $heroBanner->image;

        if ($request->hasFile('image')) {

            if (
                $heroBanner->image &&
                file_exists(public_path($heroBanner->image))
            ) {
                unlink(public_path($heroBanner->image));
            }

            $image = $request->file('image');

            $filename = time() . '.' .
                $image->getClientOriginalExtension();

            $image->move(
                public_path('uploads/hero-banner'),
                $filename
            );

            $imagePath =
                'uploads/hero-banner/' . $filename;
        }

        $heroBanner->update([

            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],

            'summary' => [
                'id' => $request->summary_id,
                'en' => $request->summary_en,
            ],

            'image' => $imagePath,

            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->is_active,
        ]);

        return redirect()
            ->route('hero-banner.index')
            ->with(
                'success',
                'Hero Banner updated successfully'
            );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HeroBanner $heroBanner)
    {
        if (
            $heroBanner->image &&
            file_exists(public_path($heroBanner->image))
        ) {

            unlink(public_path($heroBanner->image));
        }

        $heroBanner->delete();

        return redirect()
            ->route('hero-banner.index')
            ->with(
                'success',
                'Hero Banner deleted successfully'
            );
    }
}
