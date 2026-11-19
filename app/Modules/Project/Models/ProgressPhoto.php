<?php

declare(strict_types=1);

namespace App\Modules\Project\Models;

use App\Modules\Core\Village\OwnedThroughParent;
use Database\Factories\ProgressPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $path
 * @property int $progress_pct
 * @property Carbon $taken_at
 */
#[Fillable(['project_id', 'path', 'progress_pct', 'taken_at'])]
#[UseFactory(ProgressPhotoFactory::class)]
final class ProgressPhoto extends Model implements OwnedThroughParent
{
    /** @use HasFactory<ProgressPhotoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'progress_pct' => 'integer',
            'taken_at' => 'immutable_datetime',
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
