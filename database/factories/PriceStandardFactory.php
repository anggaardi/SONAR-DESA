<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceStandard>
 */
final class PriceStandardFactory extends Factory
{
    protected $model = PriceStandard::class;

    public function definition(): array
    {
        return [
            'regency' => 'Gianyar',
            'code' => 'MAT-'.fake()->unique()->numerify('####'),
            'name' => 'Semen Portland 50 kg',
            'unit' => 'sak',
            'price_rp' => 65_000,
            'year' => 2026,
            'source_ref' => 'Perbup Gianyar No. 15 Tahun 2026 hal. 12',
        ];
    }
}
