<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

use App\Modules\Audit\Enums\PriceLabel;

final readonly class LabelResult
{
    /**
     * @param  list<string>  $doubtFields
     */
    public function __construct(
        public PriceLabel $label,
        public ?int $overPct = null,
        public ?int $diffRp = null,
        public array $doubtFields = [],
        public ?string $reason = null,
    ) {}
}
