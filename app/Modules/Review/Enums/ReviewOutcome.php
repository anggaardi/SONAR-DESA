<?php

declare(strict_types=1);

namespace App\Modules\Review\Enums;

enum ReviewOutcome: string
{
    case FollowedUp = 'followed_up';
    case NoMismatch = 'no_mismatch';
}
