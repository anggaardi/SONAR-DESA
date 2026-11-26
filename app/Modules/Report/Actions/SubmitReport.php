<?php

declare(strict_types=1);

namespace App\Modules\Report\Actions;

use App\Modules\Core\Auth\User;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Domain\DistanceCalculator;
use App\Modules\Report\Domain\ReportClassifier;
use App\Modules\Report\Domain\ReportSubmission;
use App\Modules\Report\Enums\MeasurementAnswer;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Report\Events\ReportSubmitted;
use App\Modules\Report\Models\Report;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Saves a Cek Spek report (PRD Fitur Inti 5.1 step 8, 5.3, C8-C10; PRD Arsitektur M4-06, F4-2, F4-4).
 *
 * One transaction with the project row locked: two sends at the same time from one account end with one active
 * report, and the M5 listener counts the threshold under the same lock. The distance is always recomputed here
 * from the position read at send time; nothing the browser computed is trusted. Out of radius is refused (D-009).
 */
final class SubmitReport
{
    public function __construct(
        private readonly DistanceCalculator $calculator,
        private readonly ReportClassifier $classifier,
        private readonly StoreReportPhotos $photos,
    ) {}

    /** The project must have a location; SubmitReportRequest returns 404 before this otherwise. */
    public function handle(Project $project, User $citizen, ReportSubmission $submission): Report
    {
        $kind = $this->classifier->kind($submission->answerValues());

        $distanceMeters = $this->calculator->meters(
            (float) $project->lat,
            (float) $project->lng,
            $submission->lat,
            $submission->lng,
        );

        $this->ensureAtProject($project, $distanceMeters);

        // Files first: a failed transaction deletes them again, so no file is left without a row.
        $photoPaths = $this->photos->store($submission->photos);

        try {
            $report = DB::transaction(fn (): Report => $this->save($project, $citizen, $submission, $kind, $distanceMeters, $photoPaths));
        } catch (Throwable $exception) {
            $this->photos->delete($photoPaths);

            throw $exception;
        }

        // No coordinates, email, or note in logs (PRD Arsitektur 8).
        Log::info('report.submitted', [
            'report_id' => $report->id,
            'project_id' => $report->project_id,
            'kind' => $report->kind->value,
        ]);

        return $report;
    }

    /**
     * Runs inside the transaction with the project row locked, so two sends at the same time are handled one after
     * the other (F4-4); the partial unique indexes of M4-02 are the database guarantee behind it.
     *
     * @param  list<string>  $photoPaths
     */
    private function save(
        Project $project,
        User $citizen,
        ReportSubmission $submission,
        ReportKind $kind,
        float $distanceMeters,
        array $photoPaths,
    ): Report {
        $lockedProject = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
        $cycleId = $lockedProject->current_cycle_id;

        $this->ensureDailyLimitNotReached($lockedProject, $citizen);
        $this->ensureDeviceNotUsedByAnotherAccount($lockedProject, $citizen, $submission->deviceHash);

        $previous = Report::query()
            ->where('project_id', $lockedProject->id)
            ->where('user_id', $citizen->id)
            ->where('cycle_id', $cycleId)
            ->whereNull('superseded_by')
            ->first();

        // C10: the old report stays visible to BPD as "digantikan". It must stop being active before the new row
        // is inserted (one-active-report index), and it points at the new id, so that id is taken first.
        $reportId = (int) DB::scalar("select nextval(pg_get_serial_sequence('reports', 'id'))");
        $previous?->update(['superseded_by' => $reportId]);

        $report = new Report([
            'project_id' => $lockedProject->id,
            'cycle_id' => $cycleId,
            'user_id' => $citizen->id,
            'kind' => $kind,
            'lat' => $submission->lat,
            'lng' => $submission->lng,
            'accuracy_m' => $submission->accuracyMeters,
            'distance_m' => round($distanceMeters, 2),
            'note' => $submission->note,
            'device_hash' => $submission->deviceHash,
        ]);
        $report->id = $reportId;
        $report->save();

        $this->saveAnswers($report, $lockedProject, $submission);

        foreach ($photoPaths as $index => $path) {
            $report->photos()->create(['path' => $path, 'position' => $index + 1]);
        }

        ReportSubmitted::dispatch($report->id, $lockedProject->id);

        return $report;
    }

    /**
     * C5: measured_value is kept only for "Tidak" in the RAB unit (optional if omitted).
     * D15: target_value & target_unit snapshot the locked project measurement at submission time.
     */
    private function saveAnswers(Report $report, Project $project, ReportSubmission $submission): void
    {
        $measurements = $project->measurements()->get(['id', 'value', 'unit'])->keyBy('id');

        foreach ($submission->answers as $answer) {
            $measurement = $measurements->get($answer->measurementId);
            $hasMeasuredValue = $answer->answer === MeasurementAnswer::Mismatch
                && $answer->measuredValue !== null
                && $answer->measuredValue !== '';

            $report->answers()->create([
                'measurement_id' => $answer->measurementId,
                'answer' => $answer->answer,
                'measured_value' => $hasMeasuredValue ? $answer->measuredValue : null,
                'measured_unit' => $hasMeasuredValue ? $measurement?->unit : null,
                'target_value' => $measurement?->value,
                'target_unit' => $measurement?->unit,
            ]);
        }
    }

    /**
     * D-010: one device, one reporter. Another account's active report from this device in this cycle refuses
     * the send; the same account on the same device simply replaces its own report (C10).
     */
    private function ensureDeviceNotUsedByAnotherAccount(Project $project, User $citizen, string $deviceHash): void
    {
        $usedByAnotherAccount = Report::query()
            ->where('project_id', $project->id)
            ->where('cycle_id', $project->current_cycle_id)
            ->where('device_hash', $deviceHash)
            ->where('user_id', '!=', $citizen->id)
            ->whereNull('superseded_by')
            ->exists();

        if ($usedByAnotherAccount) {
            Log::info('report.rejected', ['project_id' => $project->id, 'reason' => 'device_used']);

            throw ValidationException::withMessages([
                'device' => 'Perangkat ini sudah dipakai akun lain untuk melaporkan proyek ini. Satu perangkat hanya untuk satu pelapor.',
            ]);
        }
    }

    /**
     * D-009: the warga must be at the project. The accuracy limit is already a validation rule
     * (SubmitReportRequest), so here only the recomputed distance is left to check.
     */
    private function ensureAtProject(Project $project, float $distanceMeters): void
    {
        if ($this->classifier->isWithinRadius($distanceMeters)) {
            return;
        }

        // No coordinates in logs (PRD Arsitektur 8).
        Log::info('report.rejected', ['project_id' => $project->id, 'reason' => 'out_of_radius']);
        $radiusMeters = (int) config('sonar.report.radius_m');

        throw ValidationException::withMessages([
            'location' => "Kamu berada di luar {$radiusMeters} m dari titik proyek. Datangi lokasi proyek, lalu kirim lagi. Isianmu tidak hilang.",
        ]);
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
