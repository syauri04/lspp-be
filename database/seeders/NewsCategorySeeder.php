<?php

namespace Database\Seeders;

use App\Models\NewsCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NewsCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [

            [
                'id' => 'Teknologi',
                'en' => 'Technology',
            ],

            [
                'id' => 'Pariwisata',
                'en' => 'Tourism',
            ],

            [
                'id' => 'Sertifikasi',
                'en' => 'Certification',
            ],

            [
                'id' => 'Kegiatan',
                'en' => 'Activities',
            ],

            [
                'id' => 'Pengumuman',
                'en' => 'Announcements',
            ],
        ];

        foreach ($categories as $category) {

            NewsCategory::create([

                'name' => $category,

                'slug' => Str::slug(
                    $category['id']
                ),

                'is_active' => true,
            ]);
        }
    }
}
