<?php

declare(strict_types=1);

namespace App\Modules\Review\Actions;

use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Review\Enums\ReviewOutcome;
use App\Modules\Review\Models\ReviewCycle;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Saves BPD's final review outcome for an InReview project, publishes it, and opens a fresh cycle (B6-B8, F14).
 */
final readonly class SubmitReviewOutcome
{
    public const int MIN_NOTE_LENGTH = 30;

    public function execute(Project $project, ReviewOutcome $outcome, string $note): ReviewCycle
    {
        $trimmedNote = trim($note);
        if (mb_strlen($trimmedNote) < self::MIN_NOTE_LENGTH) {
            throw new DomainException('Catatan hasil tinjauan wajib minimal 30 huruf.');
        }

        return DB::transaction(function () use ($project, $outcome, $trimmedNote): ReviewCycle {
            $project = Project::query()->with('currentCycle')->lockForUpdate()->findOrFail($project->id);

            if ($project->public_status !== PublicStatus::InReview) {
                throw new DomainException('Hasil tinjauan hanya bisa disimpan saat proyek berstatus Dalam tinjauan BPD.');
            }

            $now = now();
            $currentCycle = $project->currentCycle;

            if (! $currentCycle instanceof ReviewCycle || $currentCycle->closed_at !== null) {
                $currentCycle = ReviewCycle::create([
                    'project_id' => $project->id,
                    'opened_at' => $now,
                    'in_review_since' => $now,
                ]);
            }

            $currentCycle->update([
                'result' => $outcome,
                'note' => $trimmedNote,
                'closed_at' => $now,
            ]);

            $nextCycle = ReviewCycle::create([
                'project_id' => $project->id,
                'opened_at' => $now,
            ]);

            $project->update([
                'public_status' => PublicStatus::Reviewed,
                'current_cycle_id' => $nextCycle->id,
            ]);

            return $currentCycle->refresh();
        });
    }
}
