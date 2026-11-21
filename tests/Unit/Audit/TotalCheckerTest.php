<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\TotalChecker;
use App\Modules\Audit\Models\AuditItem;

beforeEach(function () {
    $this->checker = new TotalChecker;
});

describe('TotalChecker', function () {
    it('returns isMatch true when calculated sum exactly equals written total', function () {
        $amounts = [10_000_000, 20_000_000, 30_000_000];
        $writtenTotal = 60_000_000;

        $result = $this->checker->check($amounts, $writtenTotal);

        expect($result->isMatch)->toBeTrue()
            ->and($result->calculatedTotalRp)->toBe(60_000_000)
            ->and($result->writtenTotalRp)->toBe(60_000_000)
            ->and($result->diffRp)->toBe(0);
    });

    it('returns isMatch true when written total is null (no comparison available in document)', function () {
        $amounts = [5_000_000, 2_500_000];

        $result = $this->checker->check($amounts, null);

        expect($result->isMatch)->toBeTrue()
            ->and($result->calculatedTotalRp)->toBe(7_500_000)
            ->and($result->writtenTotalRp)->toBeNull()
            ->and($result->diffRp)->toBe(0);
    });

    it('returns isMatch false when calculated sum differs from written total', function () {
        // e.g. written total in RAB header says Rp105.000.000, but sum of lines is Rp100.000.000
        $amounts = [50_000_000, 50_000_000];
        $writtenTotal = 105_000_000;

        $result = $this->checker->check($amounts, $writtenTotal);

        expect($result->isMatch)->toBeFalse()
            ->and($result->calculatedTotalRp)->toBe(100_000_000)
            ->and($result->writtenTotalRp)->toBe(105_000_000)
            ->and($result->diffRp)->toBe(5_000_000);
    });

    it('respects tolerance parameter when specified', function () {
        $amounts = [10_000_500];
        $writtenTotal = 10_000_000;

        // Diff 500 <= tolerance 1000
        $resultWithTolerance = $this->checker->check($amounts, $writtenTotal, toleranceRp: 1000);
        expect($resultWithTolerance->isMatch)->toBeTrue()
            ->and($resultWithTolerance->diffRp)->toBe(500);

        // Diff 500 > tolerance 100
        $resultStrict = $this->checker->check($amounts, $writtenTotal, toleranceRp: 100);
        expect($resultStrict->isMatch)->toBeFalse()
            ->and($resultStrict->diffRp)->toBe(500);
    });

    it('calculates sum from array items containing amount_rp key', function () {
        $items = [
            ['amount_rp' => 12_000_000],
            ['amount_rp' => 8_000_000],
            ['amount_rp' => null],
        ];

        $result = $this->checker->check($items, 20_000_000);

        expect($result->isMatch)->toBeTrue()
            ->and($result->calculatedTotalRp)->toBe(20_000_000);
    });

    it('calculates sum correctly for empty item list', function () {
        $result = $this->checker->check([], 0);

        expect($result->isMatch)->toBeTrue()
            ->and($result->calculatedTotalRp)->toBe(0)
            ->and($result->diffRp)->toBe(0);
    });
});
