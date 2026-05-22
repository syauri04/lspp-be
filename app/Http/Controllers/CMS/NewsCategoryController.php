<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\NewsCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsCategoryController extends Controller
{


    public function index()
    {
        $query = NewsCategory::query();
        $categories = $query
            ->orderBy('id', 'desc')
            ->get();
        return view('cms.news-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('cms.news-categories.form', [
            'category' => new NewsCategory(),
            'action' => route('news-categories.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name_id' => 'required',
            'name_en' => 'required',
        ]);

        $slug = Str::slug($request->name_id);
        $count = NewsCategory::where('slug', 'like', "{$slug}%")->count();
        $slug = $count ? "{$slug}-" . ($count + 1) : $slug;
        NewsCategory::create([
            'name' => [
                'id' => $request->name_id,
                'en' => $request->name_en,
            ],

            'slug' => $slug,

            'is_active' => $request->is_active ?? false,
        ]);

        return redirect()
            ->route('news-categories.index')
            ->with(
                'success',
                'Category created'
            );
    }

    public function edit(string $id)
    {
        $data = NewsCategory::findOrFail($id);
        return view('cms.news-categories.form', [
            'data' => $data,
            'action' => route('news-categories.update', $id),
            'method' => 'PUT', // Laravel method spoofing
        ]);
    }

    public function update(
        Request $request,
        NewsCategory $newsCategory
    ) {
        $request->validate([
            'name_id' => 'required',
            'name_en' => 'required',
        ]);


        if ($newsCategory->name['id'] !== $request->name_id) {
            $slug = Str::slug($request->name_id);

            $count = NewsCategory::where('slug', 'like', "{$slug}%")
                ->where('id', '!=', $newsCategory->id)
                ->count();

            $slug = $count ? "{$slug}-" . ($count + 1) : $slug;
        } else {
            $slug = $newsCategory->slug;
        }


        $newsCategory->update([
            'name' => [
                'id' => $request->name_id,
                'en' => $request->name_en,
            ],

            'slug' => $slug,

            'is_active' => $request->is_active,
        ]);

        return redirect()
            ->route('news-categories.index')
            ->with(
                'success',
                'Category updated'
            );
    }

    public function destroy(NewsCategory $newsCategory)
    {
        $newsCategory->delete();

        return redirect()
            ->route('news-categories.index')
            ->with(
                'success',
                'Category deleted successfully'
            );
    }
}
