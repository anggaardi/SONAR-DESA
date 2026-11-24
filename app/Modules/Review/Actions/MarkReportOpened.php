<?php

declare(strict_types=1);

namespace App\Modules\Review\Actions;

use App\Modules\Report\Models\Report;

/**
 * Marks a report as opened by BPD so the "baru" indicator clears (PRD Fitur 6.1 step 4).
 */
final readonly class MarkReportOpened
{
    public function execute(Report $report): Report
    {
        if ($report->opened_at === null) {
            $report->update(['opened_at' => now()]);
        }

        return $report->refresh();
    }
}
