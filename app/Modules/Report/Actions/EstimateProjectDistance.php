<?php

declare(strict_types=1);

namespace App\Modules\Report\Actions;

use App\Modules\Project\Models\Project;
use App\Modules\Report\Domain\DistanceCalculator;
use App\Modules\Report\Domain\DistanceHint;
use App\Modules\Report\Domain\ReportClassifier;

/**
 * Distance hint for the Cek Spek location screen (PRD Fitur Inti 5.1 step 3). Only a hint: the official
 * distance is recomputed from fresh coordinates when the report is submitted (M4-06).
 */
final class EstimateProjectDistance
{
    public function __construct(
        private readonly DistanceCalculator $calculator,
        private readonly ReportClassifier $classifier,
    ) {}

    /** The project must have a location; callers check `$project->lat` and `$project->lng` first. */
    public function handle(Project $project, float $lat, float $lng): DistanceHint
    {
        $exactMeters = $this->calculator->meters((float) $project->lat, (float) $project->lng, $lat, $lng);

        return DistanceHint::from(
            exactMeters: $exactMeters,
            withinRadius: $this->classifier->isWithinRadius($exactMeters),
            roundUpToMeters: (int) config('sonar.report.distance_hint_round_m'),
            maxMeters: (int) config('sonar.report.distance_hint_max_m'),
        );
    }
}
