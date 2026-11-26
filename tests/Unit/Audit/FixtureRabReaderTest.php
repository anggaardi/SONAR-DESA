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

    it('matches sample PDF filenames to their dedicated fixture JSON files', function (string $filename, int $expectedTotal, int $expectedCount) {
        $standards = [
            new StandardRef(code: 'MAT-SMN-01', name: 'Semen Portland Komposit 50kg', unit: 'sak'),
            new StandardRef(code: 'MAT-PSR-01', name: 'Pasir Pasang / Pasir Cor', unit: 'm3'),
            new StandardRef(code: 'MAT-BPC-01', name: 'Batu Pecah 2/3', unit: 'm3'),
            new StandardRef(code: 'MAT-BSI-01', name: 'Besi Beton Polos dia 10mm', unit: 'btg'),
            new StandardRef(code: 'MAT-BTK-01', name: 'Batu Kali Bulat', unit: 'm3'),
            new StandardRef(code: 'MAT-SPL-01', name: 'Batu Split / Kerikil 2/3', unit: 'm3'),
        ];

        $result = $this->reader->read("audits/{$filename}", $standards);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->writtenTotalRp)->toBe($expectedTotal)
            ->and($result->rows)->toHaveCount($expectedCount);
    })->with([
        ['sample-rab-melati.pdf', 114_980_000, 14],
        ['sample-rab-subak-uma.pdf', 64_500_000, 15],
        ['sample-rab-posyandu.pdf', 68_900_000, 15],
        ['demo-sample-VOSyRAHDpt.pdf', 114_980_000, 14],
    ]);
});
