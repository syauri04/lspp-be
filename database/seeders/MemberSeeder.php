<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\Member;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $divisions = Division::all();

        foreach ($divisions as $division) {

            for ($i = 1; $i <= 4; $i++) {

                Member::create([
                    'division_id' => $division->id,

                    'name' => 'Anggota ' . $i . ' Division ' . $division->name['id'],

                    'position' => [
                        'id' => 'Staff Divisi',
                        'en' => 'Division Staff',
                    ],

                    'photo' =>
                    'uploads/organization/sample' . $i . 'png',

                    'linkedin_url' =>
                    'https://linkedin.com/in/anggota-' .
                        $division->id .
                        '-' .
                        $i,

                    'instagram_url' =>
                    'https://instagram.com/anggota_' .
                        $division->id .
                        '_' .
                        $i,

                    'sort_order' => $i,

                    'is_active' => true,
                ]);
            }
        }
    }
}
