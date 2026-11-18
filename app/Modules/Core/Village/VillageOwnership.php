<?php

declare(strict_types=1);

namespace App\Modules\Core\Village;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Access check for child tables without village_id (reports, audit_items, review_cycles, ...).
 * Global scope does not protect them, so every route taking their ID must call this (C1).
 *
 * The model must expose a `villageId()` method that walks to its parent,
 * e.g. Report::villageId() => $this->project->village_id.
 */
final class VillageOwnership
{
    public function __construct(private readonly CurrentVillage $current) {}

    /** Responds 404 (not 403) so another village's records are not revealed. */
    public function assert(Model&OwnedThroughParent $model): void
    {
        if ($model->villageId() !== $this->current->id()) {
            throw new NotFoundHttpException;
        }
    }
}
