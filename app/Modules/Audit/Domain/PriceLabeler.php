<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

use App\Modules\Audit\Enums\PriceLabel;
use BcMath\Number;

/**
 * Pure PHP domain service to determine price comparison labels,
 * percentage over standard, and rupiah difference deterministically.
 *
 * Invariant: "Numbers by code, words by LLM" (PRD Arsitektur 9.2, Decision A11).
 */
final readonly class PriceLabeler
{
    public function __construct(
        private int $defaultToleranceRp = 1000,
    ) {}

    /**
     * Determine label, over percentage, and difference rupiah for an audit item.
     *
     * @param  list<string>  $doubtFields
     */
    public function label(
        string $rawName,
        ?string $volume,
        ?string $unit,
        ?int $unitPriceRp,
        ?int $amountRp,
        ?int $standardPriceRp = null,
        ?string $standardUnit = null,
        array $doubtFields = [],
        ?int $rowToleranceRp = null,
    ): LabelResult {
        $tolerance = $rowToleranceRp ?? $this->defaultToleranceRp;

        // 1. Missing or invalid fundamental values (PRD Fitur 3.4)
        if ($unitPriceRp === null || $unitPriceRp < 0 || $volume === null || $volume === '' || ! is_numeric($volume)) {
            return new LabelResult(
                label: PriceLabel::NeedsCheck,
                doubtFields: $doubtFields,
                reason: 'nilai_tidak_lengkap',
            );
        }

        // 2. AI expressed doubt on specific fields (e.g. blurry image / ambiguous text)
        if (! empty($doubtFields)) {
            return new LabelResult(
                label: PriceLabel::NeedsCheck,
                doubtFields: $doubtFields,
                reason: 'ai_ragu',
            );
        }

        // 3. Unit mismatch between RAB and standard price reference (e.g. sak vs kg, no auto conversion)
        if ($standardPriceRp !== null && $standardUnit !== null && ! empty($unit)) {
            $normalizedItemUnit = mb_strtolower(trim($unit));
            $normalizedStandardUnit = mb_strtolower(trim($standardUnit));

            if ($normalizedItemUnit !== $normalizedStandardUnit) {
                return new LabelResult(
                    label: PriceLabel::NeedsCheck,
                    doubtFields: ['unit'],
                    reason: 'satuan_berbeda',
                );
            }
        }

        // 4. Row multiplication verification: |(volume * unit_price) - amount| > tolerance
        if ($amountRp !== null) {
            $expectedAmount = bcmul($volume, (string) $unitPriceRp, 4);
            $deviation = abs((float) bcsub($expectedAmount, (string) $amountRp, 4));

            if ($deviation > $tolerance) {
                return new LabelResult(
                    label: PriceLabel::NeedsCheck,
                    doubtFields: ['amount_rp'],
                    reason: 'deviasi_perkalian',
                );
            }
        }

        // 5. No reference available in standard catalog
        if ($standardPriceRp === null || $standardPriceRp <= 0) {
            return new LabelResult(
                label: PriceLabel::NoReference,
                reason: 'tanpa_acuan',
            );
        }

        // 6. Within standard (RAB price <= standard price)
        if ($unitPriceRp <= $standardPriceRp) {
            return new LabelResult(
                label: PriceLabel::WithinStandard,
            );
        }

        // 7. Above standard (RAB price > standard price)
        // PRD Fitur Inti 3.4: Round up so "+0%" never appears. Edge case: +0.3% -> +1%.
        $diffPerUnit = $unitPriceRp - $standardPriceRp;

        if (class_exists(Number::class)) {
            $numDiff = new Number((string) $diffPerUnit);
            $num100 = new Number('100');
            $numStd = new Number((string) $standardPriceRp);
            $pctRaw = (string) $numDiff->mul($num100)->div($numStd, 4);
        } else {
            $pctRaw = bcdiv((string) ($diffPerUnit * 100), (string) $standardPriceRp, 4);
        }

        $overPct = (int) ceil((float) $pctRaw);
        if ($overPct < 1) {
            $overPct = 1;
        }

        // diff_rp = (unitPriceRp - standardPriceRp) * volume
        $diffRpRaw = bcmul((string) $diffPerUnit, $volume, 0);
        $diffRp = (int) $diffRpRaw;

        return new LabelResult(
            label: PriceLabel::AboveStandard,
            overPct: $overPct,
            diffRp: $diffRp,
        );
    }
}
