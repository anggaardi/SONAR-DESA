<?php

declare(strict_types=1);

namespace App\Modules\Project\Http\Controllers;

use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Project\Actions\FindPublicProject;
use App\Modules\Project\Http\Resources\ProjectResource;
use App\Modules\Project\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

final class ProjectController
{
    public function show(Request $request, string $slug, FindPublicProject $findProject): Response|InertiaResponse
    {
        $project = $findProject->handle($request, $slug);

        // PRD Fitur Inti 4.2 (P10): non-existent or unpublished project displays friendly message instead of 500 error.
        if ($project === null) {
            return Inertia::render('project/NotFound', [
                'message' => 'Proyek belum diterbitkan atau tidak ditemukan',
            ])->toResponse($request)->setStatusCode(404);
        }

        app(CurrentVillage::class)->set($project->village_id);

        $project->load([
            'audit.items.standard',
            'measurements',
            'progressPhotos',
            'projectChanges',
            'reports.answers.measurement',
            'currentCycle.notes',
        ]);

        return Inertia::render('project/Show', [
            'project' => (new ProjectResource($project))->resolve($request),
        ]);
    }
}
