<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

final readonly class TotalCheckResult
{
    public function __construct(
        public int $calculatedTotalRp,
        public ?int $writtenTotalRp,
        public bool $isMatch,
        public int $diffRp,
    ) {}
}
