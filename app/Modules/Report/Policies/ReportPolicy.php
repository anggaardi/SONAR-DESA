<?php

declare(strict_types=1);

namespace App\Modules\Report\Policies;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;

final class ReportPolicy
{
    /** Only warga send Cek Spek reports (PRD Fitur Inti 5); desa and BPD accounts never report on a project. */
    public function create(User $user): bool
    {
        return $user->hasRole(Role::Warga);
    }
}
