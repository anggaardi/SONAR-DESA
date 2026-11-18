<?php

declare(strict_types=1);

namespace App\Modules\Core\Village\Exceptions;

use LogicException;

/**
 * Thrown when village-scoped data is queried without a CurrentVillage.
 * Cross-village code (demo purge, cloner) must call withoutGlobalScope() explicitly.
 */
final class VillageContextMissing extends LogicException
{
    public function __construct()
    {
        parent::__construct('Village-scoped query without CurrentVillage. Set it via middleware or WithVillage.');
    }
}
