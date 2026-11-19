<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Enums\ProjectType;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
final class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $name = 'Pembangunan Jalan '.fake()->streetName();

        return [
            'village_id' => Village::factory(),
            'audit_id' => Audit::factory(),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'name' => $name,
            'type' => ProjectType::RabatBeton,
            'lat' => -8.5321000,
            'lng' => 115.3214000,
            'fund_source' => 'Dana Desa 2026',
            'year' => 2026,
            'progress_pct' => 0,
            'current_cycle_id' => null,
            'public_status' => PublicStatus::None,
            'published_at' => Carbon::now(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => [
            'published_at' => null,
        ]);
    }

    public function inReview(): static
    {
        return $this->state(fn () => [
            'public_status' => PublicStatus::InReview,
        ]);
    }

    public function reviewed(): static
    {
        return $this->state(fn () => [
            'public_status' => PublicStatus::Reviewed,
        ]);
    }
}
