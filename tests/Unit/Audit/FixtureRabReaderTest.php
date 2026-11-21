<?php

declare(strict_types=1);

use App\Modules\Audit\Reading\FixtureRabReader;
use App\Modules\Audit\Reading\StandardRef;

beforeEach(function () {
    $this->reader = new FixtureRabReader;
    $this->standards = [
        new StandardRef(code: 'MAT-001', name: 'Semen Portland 50 kg', unit: 'sak'),
        new StandardRef(code: 'MAT-002', name: 'Pasir Beton', unit: 'm3'),
    ];
});

describe('FixtureRabReader', function () {
    it('reads pre-cached extraction from fixture JSON', function () {
        // Will match default contoh-rab-1.json fixture
        $result = $this->reader->read('sample.pdf', $this->standards);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->writtenTotalRp)->toBe(105_000_000)
            ->and(count($result->rows))->toBeGreaterThanOrEqual(5)
            ->and($result->rows[0]->rawName)->toBe('Semen Portland 50 kg')
            ->and($result->rows[0]->standardCode)->toBe('MAT-001');
    });

    it('retains doubt_fields from fixture data', function () {
        $result = $this->reader->read('sample.pdf', $this->standards);

        // Position 4 (Kayu Bekisting) has doubt on volume in contoh-rab-1.json
        $kayuRow = collect($result->rows)->first(fn ($r) => $r->rawName === 'Kayu Bekisting');
        expect($kayuRow)->not->toBeNull()
            ->and($kayuRow->doubtFields)->toContain('volume');
    });
});
