<?php

declare(strict_types=1);

use App\Modules\Report\Domain\ReportClassifier;
use App\Modules\Report\Enums\MeasurementAnswer;
use App\Modules\Report\Enums\ReportKind;

// PRD Fitur Inti 5.3: radius 300 m, accuracy 100 m. Passed in explicitly so these tests do not depend on config.
function classifier(): ReportClassifier
{
    return new ReportClassifier(radiusMeters: 300, maxAccuracyMeters: 100);
}

it('classifies the report kind from the answers', function (array $answers, ReportKind $expected) {
    expect(classifier()->kind($answers))->toBe($expected);
})->with([
    'a single Tidak' => [[MeasurementAnswer::Mismatch], ReportKind::Mismatch],
    'one Tidak among many Sesuai' => [
        [MeasurementAnswer::Match, MeasurementAnswer::Mismatch, MeasurementAnswer::Match],
        ReportKind::Mismatch,
    ],
    'Tidak with an unmeasurable answer' => [
        [MeasurementAnswer::Unmeasurable, MeasurementAnswer::Mismatch],
        ReportKind::Mismatch,
    ],
    'only Sesuai' => [[MeasurementAnswer::Match, MeasurementAnswer::Match], ReportKind::Match],
    'Sesuai with an unmeasurable answer' => [
        [MeasurementAnswer::Unmeasurable, MeasurementAnswer::Match],
        ReportKind::Match,
    ],
    'only Tidak bisa ukur' => [
        [MeasurementAnswer::Unmeasurable, MeasurementAnswer::Unmeasurable],
        ReportKind::Unmeasurable,
    ],
]);

it('rejects a report without answers', function () {
    classifier()->kind([]);
})->throws(InvalidArgumentException::class);

it('treats the radius limit as inclusive', function (float $distanceMeters, bool $expected) {
    expect(classifier()->isWithinRadius($distanceMeters))->toBe($expected);
})->with([
    'on the spot' => [0.0, true],
    'just inside' => [299.9, true],
    'exactly 300 m' => [300.0, true],
    'just outside' => [300.1, false],
    'far away' => [5000.0, false],
]);

it('treats the accuracy limit as inclusive', function (float $accuracyMeters, bool $expected) {
    expect(classifier()->hasUsableAccuracy($accuracyMeters))->toBe($expected);
})->with([
    'precise fix' => [5.0, true],
    'exactly 100 m' => [100.0, true],
    'just over' => [100.1, false],
]);

it('counts a report only when both distance and accuracy are within limits', function (float $distanceMeters, float $accuracyMeters, bool $expected) {
    expect(classifier()->isCounted($distanceMeters, $accuracyMeters))->toBe($expected);
})->with([
    'PRD edge case: 280 m with 90 m accuracy' => [280.0, 90.0, true],
    'both exactly at the limit' => [300.0, 100.0, true],
    'distance over, accuracy fine' => [300.1, 10.0, false],
    'distance fine, accuracy over' => [10.0, 100.1, false],
    'both over' => [500.0, 150.0, false],
]);
