<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            [
                'email' => 'superadmin@lspp306.com'
            ],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('P4ssw0rd123!')
            ]
        );

        $user->assignRole('super-admin');
    }
}
