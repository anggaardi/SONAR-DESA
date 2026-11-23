<?php

declare(strict_types=1);

namespace App\Modules\Project\Policies;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;

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

    /**
     * D10: Proyek terbit tidak bisa ditarik ke draf atau dihapus Pemdes.
     */
    public function delete(User $user, Project $project): bool
    {
        return ! $project->isPublished() && $this->manage($user, $project);
    }

    /**
     * D11: Ukuran Cek Spek tidak bisa dihapus pasca-terbit demi melindungi integritas jawaban warga.
     */
    public function deleteMeasurement(User $user, Project $project, ?ProjectMeasurement $measurement = null): bool
    {
        return ! $project->isPublished() && $this->manage($user, $project);
    }

    /**
     * D12: Pembekuan saat Dalam Tinjauan BPD (public_status = in_review).
     * CCO hanya berlaku untuk proyek yang sudah terbit dan tidak sedang dalam tinjauan BPD.
     */
    public function updateCco(User $user, Project $project): bool
    {
        return $project->isPublished()
            && $this->manage($user, $project)
            && $project->public_status !== PublicStatus::InReview;
    }
}
