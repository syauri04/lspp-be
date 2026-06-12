<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsArticleController extends Controller
{
    public function index()
    {
        $articles = NewsArticle::with('category')
            ->latest()
            ->get();

        return view(
            'cms.news-articles.index',
            compact('articles')
        );
    }

    public function create()
    {

        return view('cms.news-articles.form', [
            'article' => new NewsArticle(),
            'action' => route('news-articles.store'),
            'categories' => NewsCategory::orderBy('name->id')->get(),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'news_category_id' =>
            'required|exists:news_categories,id',

            'title_id' => 'required',
            'title_en' => 'required',

            'content_id' => 'required',
            'content_en' => 'required',
        ]);

        $image = null;

        if ($request->hasFile('image')) {

            $file = $request->file('image');

            $filename = time() . '.' .
                $file->getClientOriginalExtension();

            $file->move(
                public_path('uploads/news'),
                $filename
            );

            $image = 'uploads/news/' . $filename;
        }

        $slug = Str::slug($request->title_id);
        $count = NewsArticle::where('slug', 'like', "{$slug}%")->count();
        $slug = $count ? "{$slug}-" . ($count + 1) : $slug;
        NewsArticle::create([

            'news_category_id' =>
            $request->news_category_id,

            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],

            'slug' => $slug,

            'summary' => [
                'id' => $request->summary_id,
                'en' => $request->summary_en,
            ],

            'content' => [
                'id' => $request->content_id,
                'en' => $request->content_en,
            ],

            'image' => $image,

            'source' => $request->source,

            'is_active' => $request->is_active ?? false,
        ]);

        return redirect()
            ->route('news-articles.index')
            ->with(
                'success',
                'Article created'
            );
    }

    public function edit(string $id)
    {
        $data = NewsArticle::findOrFail($id);
        return view('cms.news-articles.form', [
            'data' => $data,
            'action' => route('news-articles.update', $id),
            'categories' => NewsCategory::orderBy('name->id')->get(),
            'method' => 'PUT', // Laravel method spoofing
        ]);
    }

    public function update(
        Request $request,
        NewsArticle $newsArticle
    ) {
        $imagePath = $newsArticle->image;

        if ($request->hasFile('image')) {

            if (
                $newsArticle->image &&
                file_exists(public_path($newsArticle->image))
            ) {
                unlink(public_path($newsArticle->image));
            }

            $image = $request->file('image');

            $filename = time() . '.' .
                $image->getClientOriginalExtension();

            $image->move(
                public_path('uploads/news'),
                $filename
            );

            $imagePath =
                'uploads/news/' . $filename;
        }

        if ($newsArticle->title['id'] !== $request->title_id) {
            $slug = Str::slug($request->title_id);

            $count = NewsArticle::where('slug', 'like', "{$slug}%")
                ->where('id', '!=', $newsArticle->id)
                ->count();

            $slug = $count ? "{$slug}-" . ($count + 1) : $slug;
        } else {
            $slug = $newsArticle->slug;
        }

        $newsArticle->update([

            'news_category_id' =>
            $request->news_category_id,

            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],

            'slug' => $slug,

            'summary' => [
                'id' => $request->summary_id,
                'en' => $request->summary_en,
            ],

            'content' => [
                'id' => $request->content_id,
                'en' => $request->content_en,
            ],

            'image' => $imagePath,

            'source' => $request->source,

            'is_active' => $request->is_active ?? false,
        ]);

        return redirect()
            ->route('news-articles.index')
            ->with(
                'success',
                'Article updated'
            );
    }

    public function destroy(NewsArticle $newsArticle)
    {
        if (
            $newsArticle->image &&
            file_exists(public_path($newsArticle->image))
        ) {

            unlink(public_path($newsArticle->image));
        }

        $newsArticle->delete();

        return redirect()
            ->route('news-articles.index')
            ->with(
                'success',
                'Article deleted successfully'
            );
    }
}
