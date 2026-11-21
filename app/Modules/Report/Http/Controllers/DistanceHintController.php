<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Controllers;

use App\Modules\Core\Village\VillageVisibility;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Actions\EstimateProjectDistance;
use App\Modules\Report\Http\Requests\EstimateDistanceRequest;
use App\Modules\Report\Http\Resources\DistanceHintResource;
use Illuminate\Http\JsonResponse;

final class DistanceHintController
{
    public function __invoke(
        EstimateDistanceRequest $request,
        string $slug,
        VillageVisibility $visibility,
        EstimateProjectDistance $action,
    ): JsonResponse {
        $project = Project::withoutGlobalScope('village')
            ->with('village')
            ->where('slug', $slug)
            ->first();

        // Same rule as the public project page (P10): draft, unknown, or another visitor's demo project is a 404.
        if ($project === null
            || ! $project->isPublished()
            || ! $visibility->allows($request, $project->village)
            || $project->lat === null
            || $project->lng === null) {
            abort(404);
        }

        $hint = $action->handle($project, (float) $request->validated('lat'), (float) $request->validated('lng'));

        // Location data: never cache.
        return (new DistanceHintResource($hint))->response()->header('Cache-Control', 'no-store, private');
    }
}
