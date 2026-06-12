<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\CategorySkemaSertifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategorySkemaSertifikasiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = CategorySkemaSertifikasi::query();
        $categories = $query
            ->orderBy('created_at')
            ->get();
        return view('cms.skema-categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('cms.skema-categories.form', [
            'skema_category' => new CategorySkemaSertifikasi(),
            'action' => route('skema-categories.store'),
            'method' => 'POST',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'kategori_id' => 'required',
            'kategori_en' => 'required',
        ]);

        $slug = Str::slug($request->kategori_id);
        $count = CategorySkemaSertifikasi::where('slug', 'like', "{$slug}%")->count();
        $slug = $count ? "{$slug}-" . ($count + 1) : $slug;

        CategorySkemaSertifikasi::create([
            'kategori' => [
                'id' => $request->kategori_id,
                'en' => $request->kategori_en,
            ],
            'slug' => $slug,
            'is_active' => $request->is_active ?? false,
        ]);

        return redirect()
            ->route('skema-categories.index')
            ->with('success', 'Category created');
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
        $data = CategorySkemaSertifikasi::findOrFail($id);
        return view('cms.skema-categories.form', [
            'data' => $data,
            'action' => route('skema-categories.update', $id),
            'method' => 'PUT', // Laravel method spoofing
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CategorySkemaSertifikasi $skema_category)
    {


        $request->validate([
            'kategori_id' => 'required',
            'kategori_en' => 'required',
        ]);


        if ($skema_category->kategori['id'] !== $request->kategori_id) {
            $slug = Str::slug($request->kategori_id);

            $count = CategorySkemaSertifikasi::where('slug', 'like', "{$slug}%")
                ->where('id', '!=', $skema_category->id)
                ->count();

            $slug = $count ? "{$slug}-" . ($count + 1) : $slug;
        } else {
            $slug = $skema_category->slug;
        }
        $skema_category->update([

            'kategori' => [
                'id' => $request->kategori_id,
                'en' => $request->kategori_en,
            ],

            'slug' => $slug,
            'is_active' => $request->is_active,
        ]);

        return redirect()
            ->route('skema-categories.index')
            ->with(
                'success',
                'Category updated successfully'
            );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CategorySkemaSertifikasi $skema_category)
    {
        $skema_category->delete();

        return redirect()
            ->route('skema-categories.index')
            ->with('success', 'Category deleted successfully');
    }
}
