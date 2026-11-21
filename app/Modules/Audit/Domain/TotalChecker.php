<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

use App\Modules\Audit\Models\AuditItem;

/**
 * Pure PHP domain service to verify overall RAB total consistency.
 *
 * Catches missing or double-counted rows without extra AI cost (PRD Fitur Inti 3.4, F7).
 */
final readonly class TotalChecker
{
    /**
     * Check if sum of all item amount_rp matches written total from RAB document.
     *
     * @param  iterable<int|AuditItem|array{amount_rp?: int|null}>  $items
     */
    public function check(iterable $items, ?int $writtenTotalRp, int $toleranceRp = 0): TotalCheckResult
    {
        $calculatedTotalStr = '0';

        foreach ($items as $item) {
            $amount = match (true) {
                is_int($item) => $item,
                $item instanceof AuditItem => $item->amount_rp ?? 0,
                is_array($item) => (int) ($item['amount_rp'] ?? 0),
                default => 0,
            };

            $calculatedTotalStr = bcadd($calculatedTotalStr, (string) $amount, 0);
        }

        $calculatedTotalRp = (int) $calculatedTotalStr;

        if ($writtenTotalRp === null) {
            return new TotalCheckResult(
                calculatedTotalRp: $calculatedTotalRp,
                writtenTotalRp: null,
                isMatch: true,
                diffRp: 0,
            );
        }

        $diffRp = abs($calculatedTotalRp - $writtenTotalRp);
        $isMatch = ($diffRp <= $toleranceRp);

        return new TotalCheckResult(
            calculatedTotalRp: $calculatedTotalRp,
            writtenTotalRp: $writtenTotalRp,
            isMatch: $isMatch,
            diffRp: $diffRp,
        );
    }
}
