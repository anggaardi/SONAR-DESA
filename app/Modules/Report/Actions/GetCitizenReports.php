<?php

declare(strict_types=1);

namespace App\Modules\Report\Actions;

use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use App\Modules\Core\Village\VillageVisibility;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Domain\CitizenReportBpdReview;
use App\Modules\Report\Domain\CitizenReportEntry;
use App\Modules\Report\Domain\CitizenReportHistory;
use App\Modules\Report\Domain\SuggestedSampleProject;
use App\Modules\Report\Enums\CitizenReportStatus;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Report\Models\Report;
use App\Modules\Review\Models\ReviewCycle;
use App\Modules\Review\Models\ReviewNote;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Loads Cek Spek report history for the authenticated citizen, synced with M5 BPD review cycles and
 * project threshold counts without leaking sensitive verification details (LS-R2, LS-R3, LS-R5).
 */
final class GetCitizenReports
{
    public function __construct(
        private readonly VillageVisibility $visibility,
    ) {}

    public function handle(Request $request, User $citizen): CitizenReportHistory
    {
        $reports = Report::query()
            ->where('user_id', $citizen->id)
            ->with([
                'cycle.notes' => static fn ($query) => $query->orderBy('created_at'),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $projectIds = $reports->pluck('project_id')->unique()->values()->all();

        /** @var Collection<int, Project> $projects */
        $projects = $projectIds === []
            ? collect()
            : Project::withoutGlobalScope('village')
                ->published()
                ->with([
                    'village',
                    'currentCycle.notes' => static fn ($query) => $query->orderBy('created_at'),
                    'reviewCycles' => static fn ($query) => $query->with([
                        'notes' => static fn ($noteQuery) => $noteQuery->orderBy('created_at'),
                    ]),
                ])
                ->whereIn('id', $projectIds)
                ->get()
                ->filter(fn (Project $project): bool => $this->visibility->allows($request, $project->village))
                ->keyBy('id');

        $visibleReports = $reports
            ->filter(static fn (Report $report): bool => $projects->has($report->project_id))
            ->values();

        $mismatchCounts = $this->countValidMismatchesByCycle($visibleReports->pluck('project_id')->unique()->values()->all());
        $threshold = (int) config('sonar.review.threshold', 3);

        $entries = [];
        $activeCount = 0;
        $reviewedOrInReviewCount = 0;

        foreach ($visibleReports as $report) {
            /** @var Project $project */
            $project = $projects->get($report->project_id);
            $status = $this->resolveStatus($report);
            $bpdReview = $this->resolveBpdReview($report, $project);

            if ($status === CitizenReportStatus::Active) {
                $activeCount++;
            }

            if ($bpdReview->status !== 'none') {
                $reviewedOrInReviewCount++;
            }

            $countKey = $this->cycleCountKey($project->id, $report->cycle_id);

            $entries[] = new CitizenReportEntry(
                report: $report,
                project: $project,
                status: $status,
                mismatchReportersCount: $mismatchCounts[$countKey] ?? 0,
                threshold: $threshold,
                bpdReview: $bpdReview,
            );
        }

        [$villageName, $villageSlug] = $this->resolveCitizenVillage($request, $citizen);
        $suggestedProject = $entries === [] ? $this->findSuggestedProject($request, $citizen) : null;

        return new CitizenReportHistory(
            entries: $entries,
            totalCount: count($entries),
            activeCount: $activeCount,
            reviewedOrInReviewCount: $reviewedOrInReviewCount,
            suggestedProject: $suggestedProject,
            villageName: $villageName,
            villageSlug: $villageSlug,
        );
    }

    private function resolveStatus(Report $report): CitizenReportStatus
    {
        if ($report->superseded_by !== null) {
            return CitizenReportStatus::Superseded;
        }

        if ($report->invalid_reason !== null) {
            return CitizenReportStatus::NotCounted;
        }

        return CitizenReportStatus::Active;
    }

    private function resolveBpdReview(Report $report, Project $project): CitizenReportBpdReview
    {
        $reportCycle = $report->cycle;

        if ($reportCycle instanceof ReviewCycle && $reportCycle->closed_at !== null) {
            return $this->closedCycleReview($reportCycle, submittedAfterReview: false);
        }

        if ($project->public_status === PublicStatus::InReview) {
            return new CitizenReportBpdReview(
                status: 'in_review',
                inReviewSince: $project->currentCycle?->in_review_since,
                outcome: null,
                note: null,
                reviewedAt: null,
                notes: [],
                submittedAfterReview: false,
            );
        }

        if ($project->public_status === PublicStatus::Reviewed) {
            $closedCycles = $project->reviewCycles
                ->filter(static fn (ReviewCycle $cycle): bool => $cycle->closed_at !== null)
                ->sortByDesc(static fn (ReviewCycle $cycle): array => [$cycle->closed_at?->getTimestamp() ?? 0, $cycle->id])
                ->values();

            if ($closedCycles->isEmpty() && $project->currentCycle?->closed_at !== null) {
                $closedCycles = collect([$project->currentCycle]);
            }

            $latestClosed = $closedCycles->first();
            if ($latestClosed instanceof ReviewCycle) {
                $submittedAfter = $report->created_at !== null
                    && $latestClosed->closed_at !== null
                    && $report->created_at->greaterThan($latestClosed->closed_at);

                return $this->closedCycleReview($latestClosed, submittedAfterReview: $submittedAfter);
            }
        }

        return new CitizenReportBpdReview(
            status: 'none',
            inReviewSince: null,
            outcome: null,
            note: null,
            reviewedAt: null,
            notes: [],
            submittedAfterReview: false,
        );
    }

    private function closedCycleReview(ReviewCycle $cycle, bool $submittedAfterReview): CitizenReportBpdReview
    {
        /** @var list<ReviewNote> $notes */
        $notes = $cycle->notes
            ->sortBy('created_at')
            ->values()
            ->all();

        return new CitizenReportBpdReview(
            status: 'reviewed',
            inReviewSince: $cycle->in_review_since,
            outcome: $cycle->result,
            note: $cycle->note,
            reviewedAt: $cycle->closed_at,
            notes: $notes,
            submittedAfterReview: $submittedAfterReview,
        );
    }

    /**
     * Batch-computes valid mismatch reporter counts for each (project_id, cycle_id) pair in a single query.
     *
     * @param  list<int>  $projectIds
     * @return array<string, int>
     */
    private function countValidMismatchesByCycle(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = Report::query()
            ->select('project_id', 'cycle_id')
            ->selectRaw('COUNT(DISTINCT user_id) AS mismatch_reporters_count')
            ->whereIn('project_id', $projectIds)
            ->where('kind', ReportKind::Mismatch)
            ->whereNull('invalid_reason')
            ->whereNull('superseded_by')
            ->groupBy('project_id', 'cycle_id')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            /** @var int $projectId */
            $projectId = (int) $row->getAttribute('project_id');
            $rawCycleId = $row->getAttribute('cycle_id');
            $cycleId = $rawCycleId !== null ? (int) $rawCycleId : null;
            $count = (int) $row->getAttribute('mismatch_reporters_count');

            $counts[$this->cycleCountKey($projectId, $cycleId)] = $count;
        }

        return $counts;
    }

    private function cycleCountKey(int $projectId, ?int $cycleId): string
    {
        return $projectId.':'.($cycleId === null ? 'null' : (string) $cycleId);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveCitizenVillage(Request $request, User $citizen): array
    {
        if ($citizen->village_id === null) {
            return [null, null];
        }

        $village = Village::query()->find($citizen->village_id);
        if (! $village instanceof Village || ! $this->visibility->allows($request, $village)) {
            return [null, null];
        }

        return [$village->name, $village->slug];
    }

    private function findSuggestedProject(Request $request, User $citizen): ?SuggestedSampleProject
    {
        if ($citizen->village_id === null) {
            return null;
        }

        $candidates = Project::withoutGlobalScope('village')
            ->published()
            ->with('village')
            ->where('village_id', $citizen->village_id)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereHas('measurements')
            ->orderBy('id')
            ->get()
            ->filter(fn (Project $project): bool => $this->visibility->allows($request, $project->village))
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        $mismatchCounts = $this->countValidMismatchesByCycle($candidates->pluck('id')->all());

        /** @var Project $best */
        $best = $candidates->sort(function (Project $a, Project $b) use ($mismatchCounts): int {
            $aNone = $a->public_status === PublicStatus::None ? 1 : 0;
            $bNone = $b->public_status === PublicStatus::None ? 1 : 0;
            if ($aNone !== $bNone) {
                return $bNone <=> $aNone;
            }

            $aCount = $mismatchCounts[$this->cycleCountKey($a->id, $a->current_cycle_id)] ?? 0;
            $bCount = $mismatchCounts[$this->cycleCountKey($b->id, $b->current_cycle_id)] ?? 0;
            if ($aCount !== $bCount) {
                return $bCount <=> $aCount;
            }

            return $a->id <=> $b->id;
        })->first();

        return new SuggestedSampleProject($best);
    }
}
