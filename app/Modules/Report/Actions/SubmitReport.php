<?php

declare(strict_types=1);

namespace App\Modules\Report\Actions;

use App\Modules\Core\Auth\User;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Domain\DistanceCalculator;
use App\Modules\Report\Domain\ReportClassifier;
use App\Modules\Report\Domain\ReportSubmission;
use App\Modules\Report\Events\ReportSubmitted;
use App\Modules\Report\Models\Report;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Saves a Cek Spek report (PRD Fitur Inti 5.1 step 8, 5.3, C8-C10; PRD Arsitektur M4-06, F4-2, F4-4).
 *
 * One transaction with the project row locked: two sends at the same time from one account end with one active
 * report, and the M5 listener counts the threshold under the same lock. The distance is always recomputed here
 * from the position read at send time; nothing the browser computed is trusted.
 */
final class SubmitReport
{
    /** Largest value the reports.distance_m column (decimal 8,2) can hold; farther away is far out of radius anyway. */
    private const MAX_STORABLE_DISTANCE_M = 999_999.99;

    public function __construct(
        private readonly DistanceCalculator $calculator,
        private readonly ReportClassifier $classifier,
    ) {}

    /** The project must have a location; SubmitReportRequest returns 404 before this otherwise. */
    public function handle(Project $project, User $citizen, ReportSubmission $submission): Report
    {
        $kind = $this->classifier->kind($submission->answerValues());

        $distanceMeters = min(
            self::MAX_STORABLE_DISTANCE_M,
            $this->calculator->meters((float) $project->lat, (float) $project->lng, $submission->lat, $submission->lng),
        );

        // C8 (default PRD Fitur v1.1, until F25 is decided): out of radius is kept with a mark and not counted.
        // A coarse fix is not counted either (PRD Fitur Inti 9: counted only with distance <= radius and
        // accuracy <= limit). in_radius is the one "counted" flag the public count and M5 read.
        $isCounted = $this->classifier->isCounted($distanceMeters, $submission->accuracyMeters);

        $report = DB::transaction(function () use ($project, $citizen, $submission, $kind, $distanceMeters, $isCounted): Report {
            $lockedProject = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            $this->ensureDailyLimitNotReached($lockedProject, $citizen);

            $previous = Report::query()
                ->where('project_id', $lockedProject->id)
                ->where('user_id', $citizen->id)
                ->where('cycle_id', $lockedProject->current_cycle_id)
                ->whereNull('superseded_by')
                ->latest('id')
                ->first();

            $report = Report::create([
                'project_id' => $lockedProject->id,
                'cycle_id' => $lockedProject->current_cycle_id,
                'user_id' => $citizen->id,
                'kind' => $kind,
                'lat' => $submission->lat,
                'lng' => $submission->lng,
                'accuracy_m' => $submission->accuracyMeters,
                'distance_m' => round($distanceMeters, 2),
                'in_radius' => $isCounted,
                'note' => $submission->note,
            ]);

            // C10: the old report stays visible to BPD as "digantikan" and is no longer counted.
            $previous?->update(['superseded_by' => $report->id]);

            // TODO(M4-02): save answers to report_answers (measured value + RAB unit from the measurement) and
            // photos to report_photos (private disk, re-encoded to WebP like StoreProgressPhoto) here, in this
            // transaction. Both tables wait for the 22 Nov decisions (tasks/m4-report.md).

            ReportSubmitted::dispatch($report->id, $lockedProject->id);

            return $report;
        });

        // No coordinates, email, or note in logs (PRD Arsitektur 8).
        Log::info('report.submitted', [
            'report_id' => $report->id,
            'project_id' => $report->project_id,
            'kind' => $report->kind->value,
            'in_radius' => $report->in_radius,
        ]);

        return $report;
    }

    /**
     * C10: at most config('sonar.report.max_per_day') sends per account per project per day, superseded ones
     * included. "Day" is the display timezone, so the limit resets at midnight WITA.
     */
    private function ensureDailyLimitNotReached(Project $project, User $citizen): void
    {
        $maxPerDay = (int) config('sonar.report.max_per_day');
        $startOfToday = CarbonImmutable::now((string) config('sonar.display_timezone'))->startOfDay()->utc();

        $sentToday = Report::query()
            ->where('project_id', $project->id)
            ->where('user_id', $citizen->id)
            ->where('created_at', '>=', $startOfToday)
            ->count();

        if ($sentToday >= $maxPerDay) {
            throw ValidationException::withMessages([
                'report' => "Kamu sudah mengirim {$maxPerDay} laporan untuk proyek ini hari ini. Coba lagi besok.",
            ]);
        }
    }
}
