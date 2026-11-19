<?php

declare(strict_types=1);

namespace App\Modules\Project\Models;

use App\Modules\Core\Village\OwnedThroughParent;
use Database\Factories\ProjectChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $field
 * @property string $item_label
 * @property string $old_value
 * @property string $new_value
 * @property Carbon $changed_at
 */
#[Fillable(['project_id', 'field', 'item_label', 'old_value', 'new_value', 'changed_at'])]
#[UseFactory(ProjectChangeFactory::class)]
final class ProjectChange extends Model implements OwnedThroughParent
{
    /** @use HasFactory<ProjectChangeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'changed_at' => 'immutable_datetime',
        ];
    }

    public function villageId(): int
    {
        return $this->project->village_id;
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
