<?php

namespace Database\Seeders;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NewsArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = NewsCategory::all();

        foreach ($categories as $category) {

            for ($i = 1; $i <= 3; $i++) {

                $titleEn =
                    $category->name['en'] .
                    ' Article ' .
                    $i;

                $titleId =
                    $category->name['id'] .
                    ' Artikel ' .
                    $i;

                NewsArticle::create([

                    'news_category_id' =>
                    $category->id,

                    'title' => [
                        'id' => $titleId,
                        'en' => $titleEn,
                    ],

                    'slug' => Str::slug(
                        $titleId
                    ),

                    'summary' => [
                        'id' =>
                        'Ringkasan artikel ' .
                            $i .
                            ' kategori ' .
                            $category->name['id'],

                        'en' =>
                        'Summary article ' .
                            $i .
                            ' category ' .
                            $category->name['en'],
                    ],

                    'content' => [

                        'id' =>
                        '<p>Ini adalah konten bahasa Indonesia untuk artikel ' .
                            $i .
                            '.</p>',

                        'en' =>
                        '<p>This is english content for article ' .
                            $i .
                            '.</p>',
                    ],

                    'image' =>
                    'uploads/news/' .
                        'news' .
                        $i .
                        '.png',

                    'source' =>
                    'https://example.com/source-' .
                        $i,

                    'is_view' =>
                    rand(10, 500),

                    'is_active' => true,
                ]);
            }
        }
    }
}
