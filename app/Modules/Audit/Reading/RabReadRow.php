<?php

declare(strict_types=1);

namespace App\Modules\Audit\Reading;

/**
 * Extracted row from RAB document (PRD Arsitektur 6.5).
 */
final readonly class RabReadRow
{
    /**
     * @param  list<string>  $doubtFields Names of fields that are blurry, damaged, or hard to read (e.g. ['volume'])
     */
    public function __construct(
        public int $position,
        public string $rawName,
        public ?string $volume,
        public ?string $unit,
        public ?int $unitPriceRp,
        public ?int $amountRp,
        public ?string $standardCode,
        public array $doubtFields = [],
    ) {}
}
