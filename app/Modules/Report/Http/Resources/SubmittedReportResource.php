<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Resources;

use App\Modules\Report\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Props for the Cek Spek confirmation (PRD Fitur Inti 5.1 step 9, C9). Shown only to the reporter, and still
 * without ids, email, coordinates, photos, or an invalid reason (ENGINEERING 2). The count and the threshold come
 * from the server, so Vue holds no rule numbers.
 *
 * @mixin Report
 */
final class SubmittedReportResource extends JsonResource
{
    public function __construct(Report $report, private readonly int $validMismatchReporters)
    {
        parent::__construct($report);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'project' => [
                'name' => $this->project->name,
                'village_name' => $this->project->village->name,
            ],
            'report' => [
                'kind' => $this->kind->value,
            ],
            'mismatch_count' => [
                'current' => $this->validMismatchReporters,
                'threshold' => (int) config('sonar.review.threshold'),
            ],
            'urls' => [
                'project' => route('projects.show', $this->project->slug),
                'check' => route('reports.check', $this->project->slug),
                'my_reports' => route('reports.mine'),
            ],
        ];
    }
}
