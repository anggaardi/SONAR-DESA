<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Village\Village;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Village>
 */
final class VillageFactory extends Factory
{
    protected $model = Village::class;

    public function definition(): array
    {
        $name = 'Desa '.fake()->unique()->lastName();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'regency' => 'Gianyar',
            'is_demo' => false,
            'demo_expires_at' => null,
        ];
    }
}
