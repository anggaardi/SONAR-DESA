<?php

declare(strict_types=1);

namespace App\Modules\Report\Domain;

use App\Modules\Report\Enums\MeasurementAnswer;

/**
 * One checklist answer as sent by the citizen (PRD Fitur Inti 5.1 step 5).
 * The measured value is only kept for "Tidak" (C5); its unit is always the RAB unit, taken from the measurement.
 */
final readonly class SubmittedAnswer
{
    public function __construct(
        public int $measurementId,
        public MeasurementAnswer $answer,
        public ?string $measuredValue,
    ) {}
}
