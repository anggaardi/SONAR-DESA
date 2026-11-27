<?php

declare(strict_types=1);

namespace App\Modules\Review\Actions;

use App\Modules\Report\Models\Report;
use App\Modules\Review\Enums\ReportVerdict;
use DomainException;

/**
 * BPD verifies a citizen report as valid or invalid during an open review cycle (B3, B5).
 * The verdict is final: a verified report can no longer be re-verified or receive a note.
 */
final readonly class MarkReportInvalid
{
    public function mark(Report $report, string $reason, ?string $note = null): Report
    {
        $this->ensureCanVerify($report);

        $trimmed = trim($reason);
        if ($trimmed === '') {
            throw new DomainException('Alasan tidak valid wajib diisi.');
        }

        $report->update([
            'invalid_reason' => $trimmed,
            'bpd_verdict' => ReportVerdict::Invalid,
            'bpd_verified_at' => now(),
            'opened_at' => $report->opened_at ?? now(),
            ...$this->noteAttributes($report, $note),
        ]);

        // B5: Do NOT lower project public_status if already InReview.
        return $report->refresh();
    }

    public function markValid(Report $report, ?string $note = null): Report
    {
        $this->ensureCanVerify($report);

        $report->update([
            'bpd_verdict' => ReportVerdict::Valid,
            'bpd_verified_at' => now(),
            'opened_at' => $report->opened_at ?? now(),
            ...$this->noteAttributes($report, $note),
        ]);

        return $report->refresh();
    }

    public function ensureCanVerify(Report $report): void
    {
        if ($report->isVerified()) {
            throw new DomainException('Laporan ini sudah diverifikasi BPD dan tidak dapat diubah lagi.');
        }
    }

    /**
     * One note per report: only stored when the report has none yet.
     *
     * @return array<string, mixed>
     */
    private function noteAttributes(Report $report, ?string $note): array
    {
        $trimmed = trim((string) $note);
        $hasNote = $report->bpd_reply !== null && trim($report->bpd_reply) !== '';

        if ($trimmed === '' || $hasNote) {
            return [];
        }

        return ['bpd_reply' => $trimmed, 'bpd_replied_at' => now()];
    }
}
