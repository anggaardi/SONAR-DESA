<?php

declare(strict_types=1);

namespace App\Modules\Review\Actions;

use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Report\Models\Report;
use App\Modules\Review\Models\ReviewCycle;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates whether a project's current review cycle has reached the mismatch threshold (B4, F12).
 *
 * Rule B5: Once a project is InReview, it never drops back below InReview until BPD submits an outcome.
 */
final readonly class EvaluateReviewThreshold
{
    public function execute(Project $project): bool
    {
        return DB::transaction(function () use ($project): bool {
            $project = Project::query()->with('currentCycle')->lockForUpdate()->findOrFail($project->id);

            if ($project->public_status === PublicStatus::InReview) {
                return true;
            }

            $cycle = $project->currentCycle;
            if (! $cycle instanceof ReviewCycle || $cycle->closed_at !== null) {
                $cycle = ReviewCycle::create([
                    'project_id' => $project->id,
                    'opened_at' => now(),
                ]);
                $project->update(['current_cycle_id' => $cycle->id]);
            }

            $threshold = (int) config('sonar.review.threshold', 3);

            $validMismatches = Report::query()
                ->where('project_id', $project->id)
                ->where('cycle_id', $cycle->id)
                ->where('kind', ReportKind::Mismatch)
                ->whereNull('invalid_reason')
                ->whereNull('superseded_by')
                ->get(['user_id', 'device_hash']);

            // Adendum v1.2 5.2: >= threshold distinct accounts AND >= threshold distinct devices.
            $distinctAccounts = $validMismatches->pluck('user_id')->unique()->count();
            $distinctDevices = $validMismatches
                ->map(static fn (Report $report): string => $report->device_hash ?? 'user:'.$report->user_id)
                ->unique()
                ->count();

            if ($distinctAccounts < $threshold || $distinctDevices < $threshold) {
                return false;
            }

            $now = now();
            $cycle->update([
                'in_review_since' => $cycle->in_review_since ?? $now,
            ]);

            $project->update([
                'public_status' => PublicStatus::InReview,
            ]);

            return true;
        });
    }
}
