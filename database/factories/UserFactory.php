<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    private static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Warga,
            'village_id' => null,
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function desa(?Village $village = null): self
    {
        return $this->state(fn () => [
            'role' => Role::Desa,
            'village_id' => $village->id ?? Village::factory(),
        ]);
    }

    public function bpd(?Village $village = null): self
    {
        return $this->state(fn () => [
            'role' => Role::Bpd,
            'village_id' => $village->id ?? Village::factory(),
        ]);
    }
}
