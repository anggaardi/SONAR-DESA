<?php

declare(strict_types=1);

namespace App\Modules\Project\Enums;

enum PublicStatus: string
{
    case None = 'none';
    case InReview = 'in_review';
    case Reviewed = 'reviewed';
}
