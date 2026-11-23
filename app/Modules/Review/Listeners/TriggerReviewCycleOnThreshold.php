<?php

declare(strict_types=1);

namespace App\Modules\Review\Listeners;

use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Actions\CountValidMismatchReporters;
use App\Modules\Report\Events\ReportSubmitted;
use App\Modules\Report\Models\Report;
use App\Modules\Review\Models\ReviewCycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Automatically triggers "Dalam Tinjauan BPD" cycle (Story S5, D16) when the count
 * of distinct valid mismatch reporters in current cycle reaches sonar.review.threshold.
 */
final class TriggerReviewCycleOnThreshold
{
    public function __construct(
        private readonly CountValidMismatchReporters $countValidMismatchReporters,
    ) {}

    public function handle(ReportSubmitted $event): void
    {
        $project = Project::withoutGlobalScope('village')->find($event->projectId);
        if ($project === null || $project->public_status === PublicStatus::InReview) {
            return;
        }

        $threshold = (int) config('sonar.review.threshold', 3);
        $mismatchCount = $this->countValidMismatchReporters->handle($project);

        if ($mismatchCount < $threshold) {
            return;
        }

        DB::transaction(function () use ($project, $threshold): void {
            $lockedProject = Project::withoutGlobalScope('village')
                ->whereKey($project->id)
                ->lockForUpdate()
                ->first();

            if ($lockedProject === null || $lockedProject->public_status === PublicStatus::InReview) {
                return;
            }

            $currentCount = $this->countValidMismatchReporters->handle($lockedProject);
            if ($currentCount < $threshold) {
                return;
            }

            $now = Carbon::now();

            $cycle = ReviewCycle::create([
                'project_id' => $lockedProject->id,
                'opened_at' => $now,
                'in_review_since' => $now,
            ]);

            // Assign unassigned reports of this project to this newly opened cycle
            Report::query()
                ->where('project_id', $lockedProject->id)
                ->whereNull('cycle_id')
                ->update(['cycle_id' => $cycle->id]);

            $lockedProject->update([
                'current_cycle_id' => $cycle->id,
                'public_status' => PublicStatus::InReview,
            ]);

            Log::info('review.cycle_opened', [
                'project_id' => $lockedProject->id,
                'cycle_id' => $cycle->id,
                'reporters' => $currentCount,
            ]);
        });
    }
}
