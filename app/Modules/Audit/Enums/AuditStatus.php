<?php

declare(strict_types=1);

namespace App\Modules\Audit\Enums;

enum AuditStatus: string
{
    case Draft = 'draft';
    case Reading = 'reading';
    case NeedsReview = 'needs_review';
    case Ready = 'ready';
    case Published = 'published';
    case ReadFailed = 'read_failed';
}
