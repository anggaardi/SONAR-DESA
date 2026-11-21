<?php

declare(strict_types=1);

namespace App\Modules\Report\Enums;

/** Why Google sign-in did not finish. Values match `googleSignIn.reasons` in resources/js/lib/labels.ts. */
enum GoogleSignInFailure: string
{
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case NotAllowed = 'not_allowed';
}
