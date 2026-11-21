<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\PriceLabeler;
use App\Modules\Audit\Enums\PriceLabel;

beforeEach(function () {
    $this->labeler = new PriceLabeler(defaultToleranceRp: 1000);
});

describe('PriceLabeler - Sesuai (WithinStandard)', function () {
    it('labels within standard when RAB unit price is strictly lower than standard price', function () {
        $result = $this->labeler->label(
            rawName: 'Pasir pasang',
            volume: '40.000',
            unit: 'm3',
            unitPriceRp: 250_000,
            amountRp: 10_000_000,
            standardPriceRp: 255_000,
            standardUnit: 'm3',
        );

        expect($result->label)->toBe(PriceLabel::WithinStandard)
            ->and($result->overPct)->toBeNull()
            ->and($result->diffRp)->toBeNull();
    });

    it('labels within standard when RAB unit price exactly matches standard price', function () {
        $result = $this->labeler->label(
            rawName: 'Semen 50 kg',
            volume: '100',
            unit: 'sak',
            unitPriceRp: 65_000,
            amountRp: 6_500_000,
            standardPriceRp: 65_000,
            standardUnit: 'sak',
        );

        expect($result->label)->toBe(PriceLabel::WithinStandard)
            ->and($result->overPct)->toBeNull()
            ->and($result->diffRp)->toBeNull();
    });
});

describe('PriceLabeler - Di atas standar (AboveStandard)', function () {
    it('calculates rounded-up percentage and total difference when unit price is above standard', function () {
        // Semen 50kg: RAB Rp85.000 vs Standar Rp65.000 (+30.76% -> 31%)
        $result = $this->labeler->label(
            rawName: 'Semen 50 kg',
            volume: '380',
            unit: 'sak',
            unitPriceRp: 85_000,
            amountRp: 32_300_000,
            standardPriceRp: 65_000,
            standardUnit: 'sak',
        );

        expect($result->label)->toBe(PriceLabel::AboveStandard)
            ->and($result->overPct)->toBe(31)
            ->and($result->diffRp)->toBe(7_600_000); // 20.000 * 380
    });

    it('always rounds up so +0% never appears (edge case: +0.3% -> +1%)', function () {
        // PRD Fitur Inti 3.4 & 9: Rp85.255 vs Rp85.000 (+0.3%) must display +1%
        $result = $this->labeler->label(
            rawName: 'Bahan Khusus',
            volume: '10',
            unit: 'sak',
            unitPriceRp: 85_255,
            amountRp: 852_550,
            standardPriceRp: 85_000,
            standardUnit: 'sak',
        );

        expect($result->label)->toBe(PriceLabel::AboveStandard)
            ->and($result->overPct)->toBe(1)
            ->and($result->diffRp)->toBe(2_550);
    });

    it('handles exact integer percentage increase cleanly', function () {
        // Rp125.000 vs Rp100.000 -> exactly +25%
        $result = $this->labeler->label(
            rawName: 'Batu Split',
            volume: '10',
            unit: 'm3',
            unitPriceRp: 125_000,
            amountRp: 1_250_000,
            standardPriceRp: 100_000,
            standardUnit: 'm3',
        );

        expect($result->label)->toBe(PriceLabel::AboveStandard)
            ->and($result->overPct)->toBe(25)
            ->and($result->diffRp)->toBe(250_000);
    });
});

describe('PriceLabeler - Tanpa acuan (NoReference)', function () {
    it('labels no reference when standardPriceRp is null', function () {
        $result = $this->labeler->label(
            rawName: 'Item Khusus Adat',
            volume: '5',
            unit: 'set',
            unitPriceRp: 500_000,
            amountRp: 2_500_000,
            standardPriceRp: null,
            standardUnit: null,
        );

        expect($result->label)->toBe(PriceLabel::NoReference)
            ->and($result->overPct)->toBeNull()
            ->and($result->diffRp)->toBeNull();
    });

    it('labels no reference when standardPriceRp is zero or negative', function () {
        $result = $this->labeler->label(
            rawName: 'Item Tanpa Harga Standar',
            volume: '5',
            unit: 'set',
            unitPriceRp: 100_000,
            amountRp: 500_000,
            standardPriceRp: 0,
        );

        expect($result->label)->toBe(PriceLabel::NoReference);
    });
});

describe('PriceLabeler - Perlu dicek (NeedsCheck)', function () {
    it('flags needs check when units do not match (no automatic conversion)', function () {
        // e.g. RAB uses 'sak' but standard uses 'kg'
        $result = $this->labeler->label(
            rawName: 'Semen Portland',
            volume: '100',
            unit: 'sak',
            unitPriceRp: 85_000,
            amountRp: 8_500_000,
            standardPriceRp: 65_000,
            standardUnit: 'kg',
        );

        expect($result->label)->toBe(PriceLabel::NeedsCheck)
            ->and($result->reason)->toBe('satuan_berbeda')
            ->and($result->doubtFields)->toContain('unit');
    });

    it('flags needs check when AI marked fields with doubt', function () {
        $result = $this->labeler->label(
            rawName: 'Kayu Bekisting',
            volume: '1.5',
            unit: 'm3',
            unitPriceRp: 2_400_000,
            amountRp: 3_600_000,
            standardPriceRp: 2_000_000,
            standardUnit: 'm3',
            doubtFields: ['volume'],
        );

        expect($result->label)->toBe(PriceLabel::NeedsCheck)
            ->and($result->reason)->toBe('ai_ragu')
            ->and($result->doubtFields)->toBe(['volume']);
    });

    it('flags needs check when row multiplication deviates beyond tolerance', function () {
        // 10 * Rp10.000 = Rp100.000, but amount is Rp150.000 (deviation Rp50.000 > tolerance Rp1.000)
        $result = $this->labeler->label(
            rawName: 'Paku 5 cm',
            volume: '10',
            unit: 'kg',
            unitPriceRp: 10_000,
            amountRp: 150_000,
            standardPriceRp: 10_000,
            standardUnit: 'kg',
            rowToleranceRp: 1000,
        );

        expect($result->label)->toBe(PriceLabel::NeedsCheck)
            ->and($result->reason)->toBe('deviasi_perkalian')
            ->and($result->doubtFields)->toContain('amount_rp');
    });

    it('accepts row multiplication when deviation is within tolerance', function () {
        // 10 * Rp10.000 = Rp100.000, amount is Rp100.500 (deviation Rp500 <= tolerance Rp1.000)
        $result = $this->labeler->label(
            rawName: 'Paku 5 cm',
            volume: '10',
            unit: 'kg',
            unitPriceRp: 10_000,
            amountRp: 100_500,
            standardPriceRp: 10_000,
            standardUnit: 'kg',
            rowToleranceRp: 1000,
        );

        expect($result->label)->toBe(PriceLabel::WithinStandard);
    });

    it('flags needs check when volume or unit price is missing or invalid', function () {
        $resultNullPrice = $this->labeler->label(
            rawName: 'Baris Tanpa Harga',
            volume: '10',
            unit: 'm3',
            unitPriceRp: null,
            amountRp: null,
        );

        expect($resultNullPrice->label)->toBe(PriceLabel::NeedsCheck)
            ->and($resultNullPrice->reason)->toBe('nilai_tidak_lengkap');

        $resultInvalidVolume = $this->labeler->label(
            rawName: 'Baris Volume Rusak',
            volume: 'bukan_angka',
            unit: 'm3',
            unitPriceRp: 50_000,
            amountRp: 500_000,
        );

        expect($resultInvalidVolume->label)->toBe(PriceLabel::NeedsCheck)
            ->and($resultInvalidVolume->reason)->toBe('nilai_tidak_lengkap');
    });
});
