<?php

namespace Database\Factories;

use App\Models\Asesi;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Asesi>
 */
class AsesiFactory extends Factory
{
    protected $model = Asesi::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => fake()->numerify('08##########'),
            'address' => fake()->address(),
            'email_verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    // Daftar via Google, belum membuat password di lspp-fe.
    public function tanpaPassword(): static
    {
        return $this->state(fn () => ['password' => null, 'provider' => 'google']);
    }
}
