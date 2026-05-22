<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsArticleDetailResource;
use App\Http\Resources\NewsArticleResource;
use App\Models\NewsArticle;
use Illuminate\Http\Request;

class NewsArticleController extends Controller
{
    /**
     * Homepage News
     * Limit 10 berita terbaru
     */
    public function home()
    {
        $news = NewsArticle::query()
            ->where('is_active', 1)
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => NewsArticleResource::collection($news),
        ], 200);
    }

    /**
     * List semua berita
     * Pagination 10 item
     */
    public function index(Request $request)
    {
        $query = NewsArticle::query()
            ->where('is_active', 1);

        /*
        |--------------------------------------------------------------------------
        | Search by title
        |--------------------------------------------------------------------------
        */
        $query->when($request->search, function ($q) use ($request) {

            $search = '%' . strtolower($request->search) . '%';

            $q->where(function ($query) use ($search) {

                $query->whereRaw(
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.id'))) LIKE ?",
                    [$search]
                )

                    ->orWhereRaw(
                        "LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.en'))) LIKE ?",
                        [$search]
                    );
            });
        });

        /*
        |--------------------------------------------------------------------------
        | Filter by category
        |--------------------------------------------------------------------------
        */
        if ($request->filled('category_id')) {

            $query->where(
                'news_category_id',
                $request->category_id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Sorting
        |--------------------------------------------------------------------------
        */
        switch ($request->sort) {

            case 'oldest':
                $query->orderBy('updated_at', 'asc');
                break;

            case 'popular':
                $query->orderBy('is_view', 'desc');
                break;

            case 'latest':
            default:
                $query->orderBy('updated_at', 'desc');
                break;
        }

        /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
        $news = $query->paginate(6);

        return response()->json([
            'success' => true,
            'message' => 'Success',

            'data' => NewsArticleResource::collection(
                $news->items()
            ),

            'meta' => [
                'current_page' => $news->currentPage(),
                'last_page' => $news->lastPage(),
                'per_page' => $news->perPage(),
                'total' => $news->total(),
            ]
        ], 200);
    }

    /**
     * Detail berita berdasarkan slug
     */
    public function detail($slug)
    {
        $news = NewsArticle::query()
            ->where('is_active', 1)
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => new NewsArticleDetailResource($news),
        ], 200);
    }
}
