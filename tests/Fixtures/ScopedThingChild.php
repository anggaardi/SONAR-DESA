<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Modules\Core\Village\OwnedThroughParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Test stand-in for a child table without village_id (C1).
 *
 * @property int $id
 * @property int $scoped_thing_id
 */
final class ScopedThingChild extends Model implements OwnedThroughParent
{
    public $timestamps = false;

    protected $fillable = ['scoped_thing_id'];

    /** @return BelongsTo<ScopedThing, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ScopedThing::class, 'scoped_thing_id');
    }

    public function villageId(): int
    {
        // The parent lookup must bypass the scope, otherwise a foreign child looks parentless.
        return (int) ScopedThing::withoutGlobalScope('village')
            ->whereKey($this->scoped_thing_id)
            ->value('village_id');
    }
}
