<?php

declare(strict_types=1);

namespace App\Modules\Project\Http\Controllers;

use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Core\Village\VillageVisibility;
use App\Modules\Project\Http\Resources\VillageProjectResource;
use App\Modules\Project\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class VillageProjectController
{
    public function __construct(private readonly VillageVisibility $visibility) {}

    public function index(Request $request, string $village): Response
    {
        $villageModel = Village::where('slug', $village)->firstOrFail();

        abort_unless($this->visibility->allows($request, $villageModel), 404, 'Desa tidak ditemukan');

        app(CurrentVillage::class)->set($villageModel->id);

        $projects = Project::where('village_id', $villageModel->id)
            ->published()
            ->with(['audit.items'])
            ->latest('published_at')
            ->get();

        return Inertia::render('project/Village', [
            'village' => [
                'name' => $villageModel->name,
                'slug' => $villageModel->slug,
                'regency' => $villageModel->regency,
            ],
            'projects' => VillageProjectResource::collection($projects)->resolve($request),
        ]);
    }
}
