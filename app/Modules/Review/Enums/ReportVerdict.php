<?php

declare(strict_types=1);

namespace App\Modules\Review\Enums;

/** Final BPD verification of one citizen report. Once set it never changes. */
enum ReportVerdict: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
}
