<?php

declare(strict_types=1);

namespace App\Modules\Review\Models;

use App\Modules\Core\Village\OwnedThroughParent;
use App\Modules\Project\Models\Project;
use App\Modules\Review\Enums\ReviewOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property Carbon $opened_at
 * @property Carbon|null $in_review_since
 * @property Carbon|null $closed_at
 * @property ReviewOutcome|null $result
 * @property string|null $note
 */
#[Fillable([
    'project_id',
    'opened_at',
    'in_review_since',
    'closed_at',
    'result',
    'note',
])]
final class ReviewCycle extends Model implements OwnedThroughParent
{
    protected function casts(): array
    {
        return [
            'opened_at' => 'immutable_datetime',
            'in_review_since' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'result' => ReviewOutcome::class,
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

    /** @return HasMany<ReviewNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(ReviewNote::class, 'cycle_id');
    }
}
