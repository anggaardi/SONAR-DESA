<?php

declare(strict_types=1);

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectChange;
use Illuminate\Support\Carbon;

/**
 * Action to record transparent CCO changes into project_changes audit trail (D13, PRD Bagian 6).
 */
final class RecordProjectChange
{
    public function execute(
        Project $project,
        string $field,
        string $itemLabel,
        string $oldValue,
        string $newValue,
        ?string $reason = null,
        ?Carbon $changedAt = null,
    ): ProjectChange {
        return ProjectChange::create([
            'project_id' => $project->id,
            'field' => $field,
            'item_label' => $itemLabel,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'changed_at' => $changedAt ?? Carbon::now(),
        ]);
    }
}
