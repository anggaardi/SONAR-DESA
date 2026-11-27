<?php

declare(strict_types=1);

namespace App\Modules\Report\Domain;

use App\Modules\Review\Enums\ReviewOutcome;
use App\Modules\Review\Models\ReviewNote;
use Carbon\CarbonInterface;

/**
 * BPD review state resolved for a single citizen report entry, synced with M5 cycles and follow-up notes.
 */
final readonly class CitizenReportBpdReview
{
    /**
     * @param  list<ReviewNote>  $notes
     */
    public function __construct(
        public string $status,
        public ?CarbonInterface $inReviewSince,
        public ?ReviewOutcome $outcome,
        public ?string $note,
        public ?CarbonInterface $reviewedAt,
        public array $notes,
        public bool $submittedAfterReview,
    ) {}
}
