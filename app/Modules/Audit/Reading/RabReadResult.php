<?php

declare(strict_types=1);

namespace App\Modules\Audit\Reading;

/**
 * Result of reading an RAB document (PRD Arsitektur 6.5).
 */
final readonly class RabReadResult
{
    /**
     * @param  list<RabReadRow>  $rows
     */
    public function __construct(
        public array $rows,
        public ?int $writtenTotalRp = null,
        public int $readMs = 0,
        public ?string $errorCode = null,
        public ?string $rawResponse = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->errorCode === null && ! empty($this->rows);
    }
}
