<?php

declare(strict_types=1);

namespace App\Modules\Report\Enums;

enum CitizenReportStatus: string
{
    case Active = 'active';
    case Superseded = 'superseded';
    case NotCounted = 'not_counted';
}
