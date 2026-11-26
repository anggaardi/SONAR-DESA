<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Controllers;

use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Project\Actions\FindPublicProject;
use App\Modules\Report\Http\Resources\CheckSpecResource;
use App\Modules\Report\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /p/{slug}/cek: the Cek Spek checklist (PRD Fitur Inti 5.1). Guests are sent to Google first and come back
 * here through url.intended (F11, bootstrap/app.php).
 */
final class CheckSpecController
{
    public function show(Request $request, string $slug, FindPublicProject $findProject): Response|InertiaResponse
    {
        $project = $findProject->handle($request, $slug);

        if ($project === null) {
            return Inertia::render('project/NotFound', [
                'message' => 'Proyek belum diterbitkan atau tidak ditemukan',
            ])->toResponse($request)->setStatusCode(404);
        }

        if (Gate::denies('create', Report::class)) {
            return Inertia::render('report/Forbidden', [
                'project' => [
                    'name' => $project->name,
                    'slug' => $project->slug,
                ],
            ])->toResponse($request)->setStatusCode(403);
        }

        app(CurrentVillage::class)->set($project->village_id);
        $project->load('measurements');

        return Inertia::render('report/Check', [
            'checkSpec' => (new CheckSpecResource($project))->resolve($request),
        ]);
    }
}
