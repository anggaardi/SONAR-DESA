<?php

declare(strict_types=1);

namespace App\Modules\Audit\Reading;

/**
 * Standard reference item provided to the AI reader.
 * Invariant: Never contains standard prices; AI only matches item names to codes (PRD Arsitektur 6.5, F6).
 */
final readonly class StandardRef
{
    public function __construct(
        public string $code,
        public string $name,
        public string $unit,
    ) {}
}
