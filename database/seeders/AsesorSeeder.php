<?php

namespace Database\Seeders;

use App\Models\Asesor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AsesorSeeder extends Seeder
{
    public function run(): void
    {
        $namaDepan = ['Budi', 'Siti', 'Ahmad', 'Dewi', 'Rudi', 'Wati', 'Agus', 'Rina', 'Hendra', 'Yuni'];
        $namaBelakang = ['Santoso', 'Wijaya', 'Kurniawan', 'Lestari', 'Pratama', 'Saputra', 'Handayani', 'Setiawan', 'Nugroho', 'Fitriani'];

        for ($i = 1; $i <= 10; $i++) {
            $nama = $namaDepan[array_rand($namaDepan)] . ' ' . $namaBelakang[array_rand($namaBelakang)];
            $slugEmail = Str::slug($nama, '.');

            Asesor::create([
                'nama' => $nama,
                'email' => "asesor{$i}.{$slugEmail}@lspp306.com",
                'password' => Hash::make('Passw0rd123!'),
                'nomor_registrasi' => 'BNSP-' . str_pad((string) (1000 + $i), 6, '0', STR_PAD_LEFT),
                'no_telepon' => '08' . random_int(1000000000, 9999999999),
                'status' => $i <= 8 ? 'aktif' : 'nonaktif', // 8 aktif, 2 nonaktif buat variasi testing filter
                'email_verified_at' => now(),
            ]);
        }

        $this->command->info('10 data dummy asesor berhasil dibuat. Password semua: Passw0rd123!');
    }
}
