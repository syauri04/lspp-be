<?php

namespace Database\Seeders;

use App\Models\HeroBanner;
use Illuminate\Database\Seeder;

class HeroBannerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [

            [
                'title' => [
                    'id' => 'Selamat Datang di LSPP 360',
                    'en' => 'Welcome to LSPP 360',
                ],

                'summary' => [
                    'id' => 'Platform sertifikasi profesi terpercaya.',
                    'en' => 'Trusted professional certification platform.',
                ],

                'image' => 'uploads/hero-banner/banner-1.png',

                'sort_order' => 1,

                'is_active' => true,
            ],

            [
                'title' => [
                    'id' => 'Sertifikasi Profesional',
                    'en' => 'Professional Certification',
                ],

                'summary' => [
                    'id' => 'Tingkatkan kompetensi Anda bersama kami.',
                    'en' => 'Improve your competency with us.',
                ],

                'image' => 'uploads/hero-banner/banner-2.png',

                'sort_order' => 2,

                'is_active' => true,
            ],

            [
                'title' => [
                    'id' => 'Asesor Berpengalaman',
                    'en' => 'Experienced Assessors',
                ],

                'summary' => [
                    'id' => 'Didukung asesor profesional bersertifikat.',
                    'en' => 'Supported by certified professional assessors.',
                ],

                'image' => 'uploads/hero-banner/banner-3.png',

                'sort_order' => 3,

                'is_active' => true,
            ],


        ];

        foreach ($items as $item) {

            HeroBanner::create($item);
        }
    }
}
