<?php

declare(strict_types=1);

namespace App\Modules\Project\Http\Controllers;

use App\Modules\Core\Http\Middleware\SetCurrentVillage;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Project\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class OriginalDocumentController
{
    /**
     * PRD Fitur Inti 4.2 Step 7 (P11, F20): stream original RAB document lazily.
     */
    public function show(Request $request, string $slug): Response
    {
        $project = Project::withoutGlobalScope('village')
            ->where('slug', $slug)
            ->with('village')
            ->firstOrFail();

        if (! $project->isPublished()) {
            abort(404, 'Dokumen asli tidak ditemukan');
        }

        if ($project->village->is_demo) {
            $demoSessionVillageId = $request->session()->get(SetCurrentVillage::DEMO_SESSION_KEY);
            if ((int) $demoSessionVillageId !== $project->village_id) {
                abort(404, 'Dokumen asli tidak ditemukan');
            }
        }

        app(CurrentVillage::class)->set($project->village_id);

        $project->load('audit');

        $filePath = $project->audit?->file_path;
        if (! $filePath) {
            abort(404, 'Dokumen asli tidak ditemukan');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($filePath)) {
            abort(404, 'File dokumen tidak ditemukan');
        }

        return $disk->response($filePath);
    }
}
