<?php

declare(strict_types=1);

namespace App\Modules\Core\Village;

use App\Modules\Core\Village\Exceptions\VillageContextMissing;

/**
 * The village whose data the current request or job may touch (PRD Arsitektur 6.7).
 *
 * Registered as a scoped singleton, so it is reset between requests and between queue jobs.
 * Jobs still set and clear it explicitly through the WithVillage job middleware.
 */
final class CurrentVillage
{
    private ?int $id = null;

    public function set(int $villageId): void
    {
        $this->id = $villageId;
    }

    public function clear(): void
    {
        $this->id = null;
    }

    public function has(): bool
    {
        return $this->id !== null;
    }

    /**
     * Fails loudly instead of returning null: a missing context must never mean "all villages".
     */
    public function id(): int
    {
        return $this->id ?? throw new VillageContextMissing;
    }
}
