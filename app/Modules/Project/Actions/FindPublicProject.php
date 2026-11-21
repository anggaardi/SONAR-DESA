<?php

declare(strict_types=1);

namespace App\Modules\Project\Actions;

use App\Modules\Core\Village\VillageVisibility;
use App\Modules\Project\Models\Project;
use Illuminate\Http\Request;

/**
 * Finds a project a public visitor may see by its slug (PRD Fitur Inti 4.2, P10).
 * Draft, unknown, or another visitor's demo project returns null, so every caller shows the same 404.
 */
final class FindPublicProject
{
    public function __construct(private readonly VillageVisibility $visibility) {}

    public function handle(Request $request, string $slug): ?Project
    {
        $project = Project::withoutGlobalScope('village')
            ->with('village')
            ->where('slug', $slug)
            ->first();

        if ($project === null || ! $project->isPublished() || ! $this->visibility->allows($request, $project->village)) {
            return null;
        }

        return $project;
    }
}
