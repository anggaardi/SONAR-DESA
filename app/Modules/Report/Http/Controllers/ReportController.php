<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Controllers;

use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Report\Actions\SubmitReport;
use App\Modules\Report\Http\Requests\SubmitReportRequest;
use Illuminate\Http\RedirectResponse;

/**
 * POST /p/{slug}/reports: send a Cek Spek report (PRD Fitur Inti 5.1 step 8, M4-06). Validation and the 404/403
 * checks are in SubmitReportRequest; the rules are in SubmitReport.
 */
final class ReportController
{
    public function store(SubmitReportRequest $request, SubmitReport $submitReport): RedirectResponse
    {
        $project = $request->project();
        app(CurrentVillage::class)->set($project->village_id);

        $submitReport->handle($project, $request->citizen(), $request->submission());

        // TODO(M4-07): go to GET /p/{slug}/reports/terkirim ("Laporanmu masuk ke BPD", x dari 3).
        return redirect()->route('projects.show', $project->slug);
    }
}
