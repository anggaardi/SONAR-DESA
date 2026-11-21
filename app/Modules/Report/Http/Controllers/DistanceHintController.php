<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Controllers;

use App\Modules\Project\Actions\FindPublicProject;
use App\Modules\Report\Actions\EstimateProjectDistance;
use App\Modules\Report\Http\Requests\EstimateDistanceRequest;
use App\Modules\Report\Http\Resources\DistanceHintResource;
use Illuminate\Http\JsonResponse;

final class DistanceHintController
{
    public function __invoke(
        EstimateDistanceRequest $request,
        string $slug,
        FindPublicProject $findProject,
        EstimateProjectDistance $action,
    ): JsonResponse {
        $project = $findProject->handle($request, $slug);

        // Same 404 as the public project page (P10), plus projects without a location point.
        if ($project === null || $project->lat === null || $project->lng === null) {
            abort(404);
        }

        $hint = $action->handle($project, (float) $request->validated('lat'), (float) $request->validated('lng'));

        // Location data: never cache.
        return (new DistanceHintResource($hint))->response()->header('Cache-Control', 'no-store, private');
    }
}
