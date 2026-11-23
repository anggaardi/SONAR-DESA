<?php

declare(strict_types=1);

namespace App\Modules\Desa\Http\Controllers;

use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Models\Report;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /desa — dashboard internal Pemerintah Desa (M2-06).
 */
final class DesaDashboardController
{
    public function index(Request $request): Response
    {
        $villageId = app(CurrentVillage::class)->id();

        /** @var Village $village */
        $village = Village::findOrFail($villageId);

        $projects = Project::where('village_id', $villageId)
            ->with(['audit', 'progressPhotos'])
            ->latest('id')
            ->get();

        $auditCounts = Audit::where('village_id', $villageId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $pendingReportsCount = Report::whereIn('project_id', $projects->pluck('id'))
            ->whereNull('invalid_reason')
            ->count();

        $inProgressAudits = Audit::where('village_id', $villageId)
            ->where('status', '!=', AuditStatus::Published)
            ->with('items')
            ->latest('id')
            ->get()
            ->map(fn (Audit $a) => [
                'id' => $a->id,
                'status' => $a->status->value,
                'source' => $a->source,
                'file_name' => $a->file_path ? basename($a->file_path) : 'Dokumen_RAB.pdf',
                'written_total_rp' => $a->written_total_rp,
                'items_count' => $a->items->count(),
                'needs_check_count' => $a->items->filter(fn ($item) => $item->label === PriceLabel::NeedsCheck && ! $item->corrected)->count(),
                'created_at' => $a->created_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('desa/Dashboard', [
            'village' => [
                'id' => $village->id,
                'name' => $village->name,
                'slug' => $village->slug,
                'regency' => $village->regency,
            ],
            'stats' => [
                'total_projects' => $projects->count(),
                'published_projects' => $projects->whereNotNull('published_at')->count(),
                'draft_projects' => $projects->whereNull('published_at')->count(),
                'pending_reports' => $pendingReportsCount,
                'audits_reading' => (int) ($auditCounts[AuditStatus::Reading->value] ?? 0),
                'audits_needs_review' => (int) ($auditCounts[AuditStatus::NeedsReview->value] ?? 0),
                'audits_ready' => (int) ($auditCounts[AuditStatus::Ready->value] ?? 0),
                'audits_read_failed' => (int) ($auditCounts[AuditStatus::ReadFailed->value] ?? 0),
            ],
            'in_progress_audits' => $inProgressAudits,
            'projects' => $projects->map(fn (Project $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'type' => $p->type->value,
                'fund_source' => $p->fund_source,
                'year' => $p->year,
                'progress_pct' => $p->progress_pct,
                'public_status' => $p->public_status->value,
                'is_published' => $p->isPublished(),
                'published_at' => $p->published_at?->toIso8601String(),
                'audit_id' => $p->audit_id,
                'audit_status' => $p->audit?->status->value,
                'photos_count' => $p->progressPhotos->count(),
            ])->values(),
        ]);
    }
}
