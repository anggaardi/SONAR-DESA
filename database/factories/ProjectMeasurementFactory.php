<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Project\Enums\MeasurementOrigin;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMeasurement>
 */
final class ProjectMeasurementFactory extends Factory
{
    protected $model = ProjectMeasurement::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => 'Tebal beton',
            'value' => '12.000',
            'unit' => 'cm',
            'how_to' => 'ukur di tepi jalan',
            'origin' => MeasurementOrigin::Template,
        ];
    }
}
