<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Controllers;

use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Project\Actions\FindPublicProject;
use App\Modules\Report\Actions\CountValidMismatchReporters;
use App\Modules\Report\Actions\SubmitReport;
use App\Modules\Report\Http\Requests\SubmitReportRequest;
use App\Modules\Report\Http\Resources\SubmittedReportResource;
use App\Modules\Report\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

final class ReportController
{
    /**
     * POST /p/{slug}/reports: send a Cek Spek report (PRD Fitur Inti 5.1 step 8, M4-06). Validation and the 404/403
     * checks are in SubmitReportRequest; the rules are in SubmitReport.
     */
    public function store(SubmitReportRequest $request, SubmitReport $submitReport): RedirectResponse
    {
        $project = $request->project();
        app(CurrentVillage::class)->set($project->village_id);

        $submitReport->handle($project, $request->citizen(), $request->submission());

        return redirect()->route('reports.submitted', $project->slug);
    }

    /**
     * GET /p/{slug}/reports/terkirim: "Laporanmu masuk ke BPD", the kind, and "x dari 3" (PRD Fitur Inti 5.1
     * step 9, C9, M4-07). Shows the account's own active report in the current cycle; without one, back to
     * the checklist.
     */
    public function submitted(
        Request $request,
        string $slug,
        FindPublicProject $findProject,
        CountValidMismatchReporters $countReporters,
    ): Response|InertiaResponse {
        $project = $findProject->handle($request, $slug);

        if ($project === null) {
            return Inertia::render('project/NotFound', [
                'message' => 'Proyek belum diterbitkan atau tidak ditemukan',
            ])->toResponse($request)->setStatusCode(404);
        }

        Gate::authorize('create', Report::class);

        app(CurrentVillage::class)->set($project->village_id);

        $report = Report::query()
            ->where('project_id', $project->id)
            ->where('user_id', $request->user()?->id)
            ->where('cycle_id', $project->current_cycle_id)
            ->whereNull('superseded_by')
            ->latest('id')
            ->first();

        if ($report === null) {
            return redirect()->route('reports.check', $project->slug);
        }

        $report->setRelation('project', $project);

        return Inertia::render('report/Submitted', [
            'submitted' => (new SubmittedReportResource($report, $countReporters->handle($project)))->resolve($request),
        ]);
    }
}
