<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Report\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Report>
 */
final class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'cycle_id' => null,
            'user_id' => User::factory()->state(['role' => Role::Warga, 'village_id' => null]),
            'kind' => ReportKind::Mismatch,
            'lat' => -8.5321000,
            'lng' => 115.3214000,
            'accuracy_m' => '15.00',
            'distance_m' => '25.00',
            'device_hash' => null,
            'note' => 'Tebal di ujung gang tampak hanya 8 cm',
            'superseded_by' => null,
            'invalid_reason' => null,
            'opened_at' => Carbon::now(),
        ];
    }
}
