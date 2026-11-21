<?php

declare(strict_types=1);

use App\Modules\Report\Domain\DistanceHint;

it('rounds the distance up to the step and drops it beyond the maximum', function (float $exactMeters, ?int $expected) {
    $hint = DistanceHint::from($exactMeters, withinRadius: true, roundUpToMeters: 10, maxMeters: 1000);

    expect($hint->distanceMeters)->toBe($expected);
})->with([
    'on the spot' => [0.0, 0],
    'a fraction of a meter' => [0.1, 10],
    'exactly one step' => [10.0, 10],
    'just over one step' => [10.1, 20],
    'exactly the maximum' => [1000.0, 1000],
    'just over the maximum' => [1000.1, null],
    'far away' => [16934.5, null],
]);

it('passes the radius verdict through unchanged', function (bool $withinRadius) {
    $hint = DistanceHint::from(50.0, $withinRadius, roundUpToMeters: 10, maxMeters: 1000);

    expect($hint->withinRadius)->toBe($withinRadius);
})->with([true, false]);
