<?php

declare(strict_types=1);

namespace App\Modules\Review\Http\Controllers;

use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Report\Models\Report;
use App\Modules\Review\Actions\AddFollowUpNote;
use App\Modules\Review\Actions\MarkReportInvalid;
use App\Modules\Review\Actions\MarkReportOpened;
use App\Modules\Review\Actions\SubmitReviewOutcome;
use App\Modules\Review\Enums\ReviewOutcome;
use App\Modules\Review\Http\Requests\InvalidateReportRequest;
use App\Modules\Review\Http\Requests\StoreFollowUpNoteRequest;
use App\Modules\Review\Http\Requests\StoreReviewOutcomeRequest;
use App\Modules\Review\Models\ReviewCycle;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard BPD & siklus tinjauan (PRD Fitur Inti Bagian 6, B1-B10).
 */
final class BpdDashboardController
{
    public function index(Request $request, CurrentVillage $currentVillage): Response
    {
        $this->ensureBpd($request);

        $projects = Project::query()
            ->published()
            ->with([
                'currentCycle',
                'audit.items',
                'reports' => static function ($query): void {
                    $query->orderBy('created_at');
                },
            ])
            ->get();

        $projectRows = $projects->map(function (Project $project): array {
            $cycleId = $project->current_cycle_id;
            $cycleReports = $project->reports->filter(
                static fn (Report $r): bool => $cycleId === null || $r->cycle_id === $cycleId
            );

            $activeReports = $cycleReports->filter(static fn (Report $r): bool => $r->superseded_by === null);

            $validMismatchCount = $activeReports
                ->filter(static fn (Report $r): bool => $r->kind === ReportKind::Mismatch && $r->invalid_reason === null)
                ->pluck('user_id')
                ->unique()
                ->count();

            $matchCount = $activeReports
                ->filter(static fn (Report $r): bool => $r->kind === ReportKind::Match && $r->invalid_reason === null)
                ->pluck('user_id')
                ->unique()
                ->count();

            $unreadCount = $activeReports->filter(static fn (Report $r): bool => $r->opened_at === null)->count();

            $aboveStandardCount = $project->audit !== null
                ? $project->audit->items->filter(static fn ($item): bool => $item->label === PriceLabel::AboveStandard)->count()
                : 0;

            return [
                'id' => $project->id,
                'slug' => $project->slug,
                'name' => $project->name,
                'public_status' => $project->public_status->value,
                'in_review_since' => $project->currentCycle?->in_review_since?->toIso8601String(),
                'above_standard_count' => $aboveStandardCount,
                'valid_mismatch_count' => $validMismatchCount,
                'match_count' => $matchCount,
                'unread_count' => $unreadCount,
            ];
        })->sort(static function (array $a, array $b): int {
            $aInReview = $a['public_status'] === PublicStatus::InReview->value ? 1 : 0;
            $bInReview = $b['public_status'] === PublicStatus::InReview->value ? 1 : 0;
            if ($aInReview !== $bInReview) {
                return $bInReview <=> $aInReview;
            }

            $aHasNew = $a['unread_count'] > 0 ? 1 : 0;
            $bHasNew = $b['unread_count'] > 0 ? 1 : 0;
            if ($aHasNew !== $bHasNew) {
                return $bHasNew <=> $aHasNew;
            }

            return $b['above_standard_count'] <=> $a['above_standard_count'];
        })->values();

        $selectedId = $request->integer('project');
        $selectedProject = $selectedId > 0
            ? $projects->firstWhere('id', $selectedId)
            : ($projectRows->isNotEmpty() ? $projects->firstWhere('id', $projectRows->first()['id']) : null);

        $selectedDetail = null;
        if ($selectedProject instanceof Project) {
            $cycleId = $selectedProject->current_cycle_id;
            $cycleReports = $selectedProject->reports
                ->filter(static fn (Report $r): bool => $cycleId === null || $r->cycle_id === $cycleId)
                ->values();

            // Anonymize reporters per project per cycle: Pelapor A, Pelapor B, Pelapor C... (B2)
            $reporterLabels = [];
            $nextIndex = 0;
            foreach ($cycleReports as $r) {
                if (! array_key_exists($r->user_id, $reporterLabels)) {
                    $reporterLabels[$r->user_id] = 'Pelapor '.$this->indexToLetters($nextIndex);
                    $nextIndex++;
                }
            }

            $closedCycles = ReviewCycle::query()
                ->where('project_id', $selectedProject->id)
                ->whereNotNull('closed_at')
                ->with(['notes' => static fn ($q) => $q->orderBy('created_at')])
                ->orderByDesc('closed_at')
                ->get()
                ->map(static fn (ReviewCycle $c): array => [
                    'id' => $c->id,
                    'result' => $c->result?->value,
                    'note' => $c->note,
                    'closed_at' => $c->closed_at?->toIso8601String(),
                    'notes' => $c->notes->map(static fn ($n): array => [
                        'id' => $n->id,
                        'note' => $n->note,
                        'created_at' => $n->created_at?->toIso8601String(),
                    ])->values()->all(),
                ])
                ->values()
                ->all();

            $selectedDetail = [
                'id' => $selectedProject->id,
                'slug' => $selectedProject->slug,
                'name' => $selectedProject->name,
                'public_status' => $selectedProject->public_status->value,
                'in_review_since' => $selectedProject->currentCycle?->in_review_since?->toIso8601String(),
                'public_url' => route('projects.show', $selectedProject->slug),
                'document_url' => route('projects.document', $selectedProject->slug),
                'reports' => $cycleReports->map(static fn (Report $r): array => [
                    'id' => $r->id,
                    'reporter_label' => $reporterLabels[$r->user_id],
                    'kind' => $r->kind->value,
                    'distance_m' => $r->distance_m !== null ? (float) $r->distance_m : null,
                    'note' => $r->note,
                    'superseded' => $r->superseded_by !== null,
                    'invalid_reason' => $r->invalid_reason,
                    'is_new' => $r->opened_at === null,
                    'created_at' => $r->created_at?->toIso8601String(),
                ])->values()->all(),
                'closed_cycles' => $closedCycles,
            ];
        }

        $village = Village::query()->findOrFail($currentVillage->id());

        return Inertia::render('review/Index', [
            'village' => [
                'name' => $village->name,
                'slug' => $village->slug,
            ],
            'projects' => $projectRows->all(),
            'selectedProject' => $selectedDetail,
            'threshold' => (int) config('sonar.review.threshold', 3),
        ]);
    }

    public function openReport(Request $request, int $reportId, MarkReportOpened $action): RedirectResponse
    {
        $this->ensureBpd($request);
        $report = $this->findVillageReport($reportId);

        $action->execute($report);

        return back();
    }

    public function invalidateReport(InvalidateReportRequest $request, int $reportId, MarkReportInvalid $action): RedirectResponse
    {
        $report = $this->findVillageReport($reportId);

        try {
            $action->mark($report, (string) $request->validated('reason'));
        } catch (DomainException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back();
    }

    public function restoreReport(Request $request, int $reportId, MarkReportInvalid $action): RedirectResponse
    {
        $this->ensureBpd($request);
        $report = $this->findVillageReport($reportId);

        try {
            $action->restore($report);
        } catch (DomainException $e) {
            return back()->withErrors(['report' => $e->getMessage()]);
        }

        return back();
    }

    public function storeOutcome(StoreReviewOutcomeRequest $request, int $projectId, SubmitReviewOutcome $action): RedirectResponse
    {
        $project = Project::query()->findOrFail($projectId);

        try {
            $action->execute(
                project: $project,
                outcome: ReviewOutcome::from((string) $request->validated('result')),
                note: (string) $request->validated('note'),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['note' => $e->getMessage()]);
        }

        return back();
    }

    public function storeFollowUpNote(StoreFollowUpNoteRequest $request, int $cycleId, AddFollowUpNote $action): RedirectResponse
    {
        $cycle = ReviewCycle::query()->with('project')->findOrFail($cycleId);
        // Ensure cycle belongs to current village (BelongsToVillage on Project).
        Project::query()->findOrFail($cycle->project_id);

        try {
            $action->execute($cycle, (string) $request->validated('note'));
        } catch (DomainException $e) {
            return back()->withErrors(['note' => $e->getMessage()]);
        }

        return back();
    }

    private function ensureBpd(Request $request): void
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->hasRole(Role::Bpd)) {
            abort(403, 'Hanya akun BPD yang dapat mengakses dashboard ini.');
        }
    }

    private function findVillageReport(int $reportId): Report
    {
        $report = Report::query()->with('cycle')->findOrFail($reportId);
        // Scope check via BelongsToVillage on Project (IDOR protection).
        Project::query()->findOrFail($report->project_id);

        return $report;
    }

    private function indexToLetters(int $index): string
    {
        $letters = '';
        $n = $index;
        do {
            $letters = chr(65 + ($n % 26)).$letters;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $letters;
    }
}
