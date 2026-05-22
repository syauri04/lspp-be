<?php

namespace Database\Seeders;

use App\Models\TUK;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TUKSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = [
            'Jakarta',
            'Bandung',
            'Surabaya',
            'Yogyakarta',
            'Semarang',
            'Bekasi',
            'Bogor',
            'Depok',
            'Tangerang',
            'Malang',
        ];

        for ($i = 1; $i <= 10; $i++) {

            TUK::create([

                'title' =>
                'TUK ' . ($i % 2 == 0
                    ? 'Mandiri'
                    : 'Sewaktu'),

                'city' =>
                $cities[$i - 1],

                'address' =>
                'Jl. Contoh Alamat No. ' .
                    $i .
                    ', ' .
                    $cities[$i - 1],

                'open_days' =>
                'Senin - Jumat',

                'open_hours' =>
                '08:00 - 17:00',

                'google_maps_url' =>
                'https://maps.google.com/?q=' .
                    urlencode($cities[$i - 1]),

                'is_active' => true,
            ]);
        }
    }
}
