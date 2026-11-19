<?php

declare(strict_types=1);

namespace App\Modules\Review\Models;

use App\Modules\Core\Village\OwnedThroughParent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cycle_id
 * @property string $note
 */
#[Fillable(['cycle_id', 'note'])]
final class ReviewNote extends Model implements OwnedThroughParent
{
    public function villageId(): int
    {
        return $this->cycle->project->village_id;
    }

    /** @return BelongsTo<ReviewCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ReviewCycle::class, 'cycle_id');
    }
}
