<?php

declare(strict_types=1);

namespace App\Modules\Review\Listeners;

use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Events\ReportSubmitted;
use App\Modules\Review\Actions\EvaluateReviewThreshold;

/**
 * Automatically triggers "Dalam Tinjauan BPD" cycle (Story S5, D16, Adendum v1.2 §5.2) via
 * EvaluateReviewThreshold when distinct valid accounts AND distinct devices reach threshold.
 */
final class TriggerReviewCycleOnThreshold
{
    public function __construct(
        private readonly EvaluateReviewThreshold $evaluateReviewThreshold,
    ) {}

    public function handle(ReportSubmitted $event): void
    {
        $project = Project::withoutGlobalScope('village')->find($event->projectId);
        if ($project === null || $project->public_status === PublicStatus::InReview) {
            return;
        }

        $this->evaluateReviewThreshold->execute($project);
    }
}
