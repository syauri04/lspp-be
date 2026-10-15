<?php

namespace Database\Factories;

use App\Models\Asesor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Asesor>
 */
class AsesorFactory extends Factory
{
    protected $model = Asesor::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'nomor_registrasi' => fake()->unique()->bothify('MET.###.######.####'),
            'no_telepon' => fake()->numerify('08##########'),
            'status' => 'aktif',
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['status' => 'nonaktif']);
    }
}
