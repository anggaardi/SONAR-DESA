<?php

declare(strict_types=1);

namespace App\Modules\Project\Http\Controllers;

use App\Modules\Core\Village\VillageVisibility;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Models\Report;
use App\Modules\Report\Models\ReportPhoto;
use App\Modules\Review\Enums\ReportVerdict;
use App\Modules\Review\Models\ReviewCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves citizen report photos publicly after BPD closes the review cycle (PublicStatus::Reviewed)
 * or verifies/replies to a valid citizen report.
 * Unreviewed, invalid, superseded, unpublished, or cross-session demo photos always return 404 (C11).
 */
final class PublishedReportPhotoController
{
    public function show(Request $request, int $photoId, VillageVisibility $visibility): Response
    {
        $photo = ReportPhoto::query()->with(['report.cycle'])->find($photoId);
        $report = $photo?->report;
        $cycle = $report?->cycle;

        if (
            ! $photo instanceof ReportPhoto
            || ! $report instanceof Report
            || $report->invalid_reason !== null
            || $report->superseded_by !== null
        ) {
            abort(404, 'Foto tidak ditemukan');
        }

        $isReviewedCycle = $cycle instanceof ReviewCycle && $cycle->closed_at !== null;
        $isBpdVerifiedOrReplied = $report->bpd_verdict === ReportVerdict::Valid
            || ($report->bpd_reply !== null && trim($report->bpd_reply) !== '');

        if (! $isReviewedCycle && ! $isBpdVerifiedOrReplied) {
            abort(404, 'Foto tidak ditemukan');
        }

        $project = Project::withoutGlobalScope('village')
            ->with('village')
            ->find($report->project_id);

        if (
            ! $project instanceof Project
            || ! $project->isPublished()
            || ($project->public_status !== PublicStatus::Reviewed && ! $isBpdVerifiedOrReplied)
            || ! $visibility->allows($request, $project->village)
        ) {
            abort(404, 'Foto tidak ditemukan');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($photo->path)) {
            abort(404, 'Foto tidak ditemukan');
        }

        return $disk->response($photo->path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Type' => 'image/webp',
        ]);
    }
}
