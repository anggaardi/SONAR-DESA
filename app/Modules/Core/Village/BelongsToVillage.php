<?php

declare(strict_types=1);

namespace App\Modules\Core\Village;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For tables with a village_id column (marked (V) in PRD Arsitektur 6.4).
 *
 * Child tables without village_id must NOT use this trait; their access is checked
 * through the parent with VillageOwnership (C1). `users` does not use it either (C3).
 *
 * @mixin Model
 */
trait BelongsToVillage
{
    public static function bootBelongsToVillage(): void
    {
        static::addGlobalScope('village', function (Builder $query): void {
            $query->where($query->qualifyColumn('village_id'), app(CurrentVillage::class)->id());
        });

        static::creating(function (Model $model): void {
            $model->setAttribute('village_id', $model->getAttribute('village_id') ?? app(CurrentVillage::class)->id());
        });
    }

    /** @return BelongsTo<Village, $this> */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }
}
