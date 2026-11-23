<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectChange;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ProjectChange>
 */
final class ProjectChangeFactory extends Factory
{
    protected $model = ProjectChange::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'field' => 'unit_price_rp',
            'item_label' => 'Semen 50 kg',
            'old_value' => 'Rp85.000',
            'new_value' => 'Rp65.000',
            'reason' => 'Penyesuaian spesifikasi di lapangan',
            'changed_at' => Carbon::now(),
        ];
    }
}
