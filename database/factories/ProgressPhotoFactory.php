<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Project\Models\ProgressPhoto;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ProgressPhoto>
 */
final class ProgressPhotoFactory extends Factory
{
    protected $model = ProgressPhoto::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'path' => 'progress/sample-'.fake()->uuid().'.webp',
            'progress_pct' => 50,
            'taken_at' => Carbon::now(),
        ];
    }
}
