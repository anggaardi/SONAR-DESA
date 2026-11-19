<?php

declare(strict_types=1);

namespace App\Modules\Report\Enums;

enum ReportKind: string
{
    case Mismatch = 'mismatch';
    case Match = 'match';
    case Unmeasurable = 'unmeasurable';
}
