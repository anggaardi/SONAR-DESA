<?php

declare(strict_types=1);

namespace App\Modules\Review\Actions;

use App\Modules\Report\Models\Report;
use DomainException;

/**
 * Marks or unmarks a citizen report as invalid during an open review cycle (B3, B5).
 */
final readonly class MarkReportInvalid
{
    public function mark(Report $report, string $reason): Report
    {
        $this->ensureCycleIsOpen($report);

        $trimmed = trim($reason);
        if ($trimmed === '') {
            throw new DomainException('Alasan tidak valid wajib diisi.');
        }

        $report->update([
            'invalid_reason' => $trimmed,
            'opened_at' => $report->opened_at ?? now(),
        ]);

        // B5: Do NOT lower project public_status if already InReview.
        return $report->refresh();
    }

    public function restore(Report $report): Report
    {
        $this->ensureCycleIsOpen($report);

        $report->update([
            'invalid_reason' => null,
        ]);

        return $report->refresh();
    }

    private function ensureCycleIsOpen(Report $report): void
    {
        $report->loadMissing('cycle');
        $cycle = $report->cycle;
        if ($cycle !== null && $cycle->closed_at !== null) {
            throw new DomainException('Siklus tinjauan sudah ditutup; status laporan tidak bisa diubah.');
        }
    }
}
