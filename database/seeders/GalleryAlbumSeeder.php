<?php

namespace Database\Seeders;

use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GalleryAlbumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 8; $i++) {

            $album = GalleryAlbum::create([

                'title' => [
                    'id' => 'Kegiatan Sertifikasi ' . $i,
                    'en' => 'Certification Activity ' . $i,
                ],
                'summary' => [
                    'id' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce cursus tincidunt velit eget pretium. Praesent condimentum, dui sit amet mattis euismod, tellus sem vehicula leo',
                    'en' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce cursus tincidunt velit eget pretium. Praesent condimentum, dui sit amet mattis euismod, tellus sem vehicula leo ',
                ],
                'slug' => Str::slug(
                    'Certification Activity ' . $i
                ),

                'cover_image' =>
                'uploads/gallery/covers/coverimage' . $i . '.png',

                'event_date' => now()
                    ->subDays($i),

                'is_active' => true,
            ]);

            // 5 Photos Per Album
            for ($x = 1; $x <= 5; $x++) {

                GalleryPhoto::create([

                    'gallery_album_id' => $album->id,

                    'image' =>
                    'uploads/gallery/photos/gallery' . $x . '.png',

                    'caption' => [
                        'id' => 'Dokumentasi kegiatan ' . $x,
                        'en' => 'Activity documentation ' . $x,
                    ],

                    'sort_order' => $x,

                    'is_active' => true,
                ]);
            }
        }
    }
}
