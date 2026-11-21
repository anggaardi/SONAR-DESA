<?php

declare(strict_types=1);

namespace App\Modules\Report\Domain;

use App\Modules\Report\Enums\MeasurementAnswer;
use Illuminate\Http\UploadedFile;

/**
 * A validated Cek Spek send (PRD Fitur Inti 5.1 steps 5-8). The position is what the browser read at send time;
 * SubmitReport recomputes the distance from it and never trusts a distance from the client (F4-2).
 */
final readonly class ReportSubmission
{
    /**
     * @param  list<SubmittedAnswer>  $answers
     * @param  list<UploadedFile>  $photos
     */
    public function __construct(
        public array $answers,
        public array $photos,
        public ?string $note,
        public float $lat,
        public float $lng,
        public float $accuracyMeters,
    ) {}

    /** @return list<MeasurementAnswer> */
    public function answerValues(): array
    {
        return array_map(static fn (SubmittedAnswer $answer) => $answer->answer, $this->answers);
    }
}
