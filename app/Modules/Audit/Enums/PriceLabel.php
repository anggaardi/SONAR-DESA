<?php

declare(strict_types=1);

namespace App\Modules\Audit\Enums;

enum PriceLabel: string
{
    case WithinStandard = 'within_standard';
    case AboveStandard = 'above_standard';
    case NoReference = 'no_reference';
    case NeedsCheck = 'needs_check';
}
