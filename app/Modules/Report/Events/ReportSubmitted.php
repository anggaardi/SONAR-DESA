<?php

declare(strict_types=1);

namespace App\Modules\Report\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A Cek Spek report was saved (PRD Arsitektur 6.1, M4-06). M4 never calls M5: M5 listens and recounts the review
 * threshold. Dispatched inside SubmitReport's transaction while the project row is locked, so a sync listener
 * counts in the same transaction. Listeners must stay sync; a queued one would run outside the lock.
 * Carries ids, not models.
 */
final readonly class ReportSubmitted
{
    use Dispatchable;

    public function __construct(
        public int $reportId,
        public int $projectId,
    ) {}
}
