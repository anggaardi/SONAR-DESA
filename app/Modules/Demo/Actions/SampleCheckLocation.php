<?php

declare(strict_types=1);

namespace App\Modules\Demo\Actions;

use App\Modules\Project\Models\Project;

/**
 * "Pakai foto & lokasi contoh" (PRD Fitur Inti 5.6, C12, M6-05): a position a little north of a demo project, so a
 * visitor who is not in Gianyar can still send a report. It goes through the same SubmitReport as a real
 * position; only the source differs (ENGINEERING 2: M4 does not know it is a demo).
 *
 * Only for projects of the visitor's own demo village: DemoController checks that before calling this.
 */
final class SampleCheckLocation
{
    /** One degree of latitude is about 111.32 km everywhere. */
    private const METERS_PER_DEGREE_LATITUDE = 111_320;

    /**
     * @return array{lat: float, lng: float, accuracy_m: int}
     */
    public function for(Project $project): array
    {
        $offsetMeters = (int) config('sonar.demo.sample_distance_m');

        return [
            'lat' => round((float) $project->lat + $offsetMeters / self::METERS_PER_DEGREE_LATITUDE, 7),
            'lng' => (float) $project->lng,
            'accuracy_m' => (int) config('sonar.demo.sample_accuracy_m'),
        ];
    }
}
