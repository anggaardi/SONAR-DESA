<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Resources;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Props for the Cek Spek page (PRD Fitur Inti 5.1, C4). Never the project coordinates: the distance comes from
 * POST /p/{slug}/cek/jarak (D-008). Limits come from config('sonar.report.*'), so Vue holds no rule numbers.
 *
 * @mixin Project
 */
final class CheckSpecResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'project' => [
                'name' => $this->name,
                'village_name' => $this->village->name,
            ],
            'measurements' => $this->measurements->map(fn (ProjectMeasurement $measurement): array => [
                'id' => $measurement->id,
                'name' => $measurement->name,
                'value' => (string) (float) $measurement->value,
                'unit' => $measurement->unit,
                'how_to' => $measurement->how_to,
            ])->all(),
            'limits' => [
                'max_accuracy_m' => (int) config('sonar.report.max_accuracy_m'),
                'max_photos' => (int) config('sonar.report.max_photos'),
                'max_note_length' => (int) config('sonar.report.max_note_length'),
            ],
            'urls' => [
                'project' => route('projects.show', $this->slug),
                'distance' => route('reports.distance', $this->slug),
                'submit' => route('reports.store', $this->slug),
            ],
        ];
    }
}
