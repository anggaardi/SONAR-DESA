<?php

declare(strict_types=1);

namespace App\Modules\Core\Village;

/**
 * Implemented by child-table models. Returns the village_id of the parent record.
 */
interface OwnedThroughParent
{
    public function villageId(): int;
}
