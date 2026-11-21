<?php

declare(strict_types=1);

namespace App\Modules\Report\Domain;

use App\Modules\Report\Enums\MeasurementAnswer;
use App\Modules\Report\Enums\ReportKind;
use InvalidArgumentException;

/**
 * Decides a report's kind and whether it counts, from fixed rules (PRD Fitur Inti 5.3, 9).
 * Limits come from config('sonar.report.*') and are injected, never hard-coded.
 */
final readonly class ReportClassifier
{
    public function __construct(
        private int $radiusMeters,
        private int $maxAccuracyMeters,
    ) {}

    /**
     * PRD Fitur Inti 5.3: any "Tidak" makes a Mismatch; otherwise any "Sesuai" makes a Match;
     * only when every measurement is "Tidak bisa ukur" is the report Unmeasurable.
     *
     * @param  list<MeasurementAnswer>  $answers
     */
    public function kind(array $answers): ReportKind
    {
        if ($answers === []) {
            throw new InvalidArgumentException('A report needs at least one answer.');
        }

        if (in_array(MeasurementAnswer::Mismatch, $answers, true)) {
            return ReportKind::Mismatch;
        }

        if (in_array(MeasurementAnswer::Match, $answers, true)) {
            return ReportKind::Match;
        }

        return ReportKind::Unmeasurable;
    }

    /** The limit is inclusive: exactly the radius still counts (PRD Fitur Inti 9). */
    public function isWithinRadius(float $distanceMeters): bool
    {
        return $distanceMeters <= $this->radiusMeters;
    }

    public function hasUsableAccuracy(float $accuracyMeters): bool
    {
        return $accuracyMeters <= $this->maxAccuracyMeters;
    }

    /** A report counts only when it is inside the radius and the GPS fix is accurate enough. */
    public function isCounted(float $distanceMeters, float $accuracyMeters): bool
    {
        return $this->isWithinRadius($distanceMeters) && $this->hasUsableAccuracy($accuracyMeters);
    }
}
