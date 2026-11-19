<?php

declare(strict_types=1);

namespace App\Modules\Project\Policies;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Project\Models\Project;

final class ProjectPolicy
{
    /** Published projects are viewable by everyone without authentication. */
    public function view(?User $user, Project $project): bool
    {
        return $project->isPublished();
    }

    /** Only desa accounts belonging to the project's village may manage it. */
    public function manage(User $user, Project $project): bool
    {
        return $user->hasRole(Role::Desa) && $user->canAccessVillage($project->village_id);
    }
}
