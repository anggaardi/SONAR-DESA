<?php

declare(strict_types=1);

namespace App\Modules\Report\Actions;

use App\Modules\Project\Models\Project;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Report\Models\Report;

/**
 * The "x" of "x dari 3" on the Cek Spek confirmation (PRD Fitur Inti 5.1 step 9, C9): distinct accounts with a
 * valid Selisih report in the project's current cycle (F12). Valid means not marked invalid by BPD and not
 * superseded by a newer report of the same account. Every saved report is inside the radius (D-009).
 *
 * Only counts. Deciding the public status stays with M5 (ReviewThreshold, M5-02), which may reuse this Action.
 */
final class CountValidMismatchReporters
{
    public function handle(Project $project): int
    {
        return Report::query()
            ->where('project_id', $project->id)
            ->where('cycle_id', $project->current_cycle_id)
            ->where('kind', ReportKind::Mismatch)
            ->whereNull('invalid_reason')
            ->whereNull('superseded_by')
            ->distinct()
            ->count('user_id');
    }
}
