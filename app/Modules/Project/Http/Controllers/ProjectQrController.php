<?php

declare(strict_types=1);

namespace App\Modules\Project\Http\Controllers;

use App\Modules\Project\Actions\GenerateProjectQrSvg;
use App\Modules\Project\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectQrController
{
    public function show(Request $request, Project $project, GenerateProjectQrSvg $qrAction): Response
    {
        Gate::authorize('manage', $project);

        $svg = $qrAction->handle($project);

        $shortUrl = url('/p/'.$project->slug);

        return Inertia::render('project/QrPoster', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'short_url' => $shortUrl,
                'shortUrl' => $shortUrl,
                'village' => [
                    'name' => $project->village->name,
                    'regency' => $project->village->regency,
                ],
            ],
            'qr_svg' => $svg,
            'qrSvg' => $svg,
            'cta_text' => 'Scan untuk lihat rincian anggaran',
            'ctaText' => 'Scan untuk lihat rincian anggaran',
        ]);
    }
}
