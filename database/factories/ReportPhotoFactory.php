<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Report\Models\Report;
use App\Modules\Report\Models\ReportPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportPhoto>
 */
final class ReportPhotoFactory extends Factory
{
    protected $model = ReportPhoto::class;

    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'path' => 'reports/sample-'.fake()->uuid().'.webp',
            'position' => 1,
        ];
    }
}
