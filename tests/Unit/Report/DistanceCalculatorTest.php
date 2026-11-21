<?php

declare(strict_types=1);

use App\Modules\Report\Domain\DistanceCalculator;

// Expected values come from an independent haversine run (R = 6371008.8 m), not from this class.
it('returns the known distance between two points', function (float $latFrom, float $lngFrom, float $latTo, float $lngTo, float $expectedMeters) {
    $meters = (new DistanceCalculator)->meters($latFrom, $lngFrom, $latTo, $lngTo);

    expect($meters)->toEqualWithDelta($expectedMeters, 0.5);
})->with([
    'one degree of longitude on the equator' => [0.0, 0.0, 0.0, 1.0, 111195.08],
    'one degree of latitude' => [0.0, 0.0, 1.0, 0.0, 111195.08],
    'longitude shrinks with latitude (Bali, -8.5)' => [-8.5, 115.3, -8.5, 115.301, 109.97],
    '300 m north along a meridian' => [-8.5321, 115.3214, -8.5294, 115.3214, 300.23],
    'Denpasar to Gianyar' => [-8.65, 115.2167, -8.5422, 115.3255, 16934.47],
]);

it('returns zero for identical points', function () {
    expect((new DistanceCalculator)->meters(-8.5321, 115.3214, -8.5321, 115.3214))->toBe(0.0);
});

it('returns the same distance in both directions', function () {
    $calculator = new DistanceCalculator;

    expect($calculator->meters(-8.65, 115.2167, -8.5422, 115.3255))
        ->toEqualWithDelta($calculator->meters(-8.5422, 115.3255, -8.65, 115.2167), 0.001);
});

it('measures across the antimeridian by the short way', function () {
    expect((new DistanceCalculator)->meters(0.0, 179.5, 0.0, -179.5))->toEqualWithDelta(111195.08, 0.5);
});
