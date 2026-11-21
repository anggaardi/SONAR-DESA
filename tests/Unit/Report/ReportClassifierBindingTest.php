<?php

declare(strict_types=1);

use App\Modules\Report\Domain\ReportClassifier;

it('reads the radius and accuracy limits from config', function () {
    config(['sonar.report.radius_m' => 50, 'sonar.report.max_accuracy_m' => 20]);

    $classifier = app(ReportClassifier::class);

    expect($classifier->isWithinRadius(50.0))->toBeTrue()
        ->and($classifier->isWithinRadius(50.1))->toBeFalse()
        ->and($classifier->hasUsableAccuracy(20.0))->toBeTrue()
        ->and($classifier->hasUsableAccuracy(20.1))->toBeFalse();
});

it('uses the PRD defaults of 300 m and 100 m', function () {
    $classifier = app(ReportClassifier::class);

    expect($classifier->isCounted(280.0, 90.0))->toBeTrue()
        ->and($classifier->isCounted(300.1, 10.0))->toBeFalse();
});
