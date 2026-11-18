<?php

declare(strict_types=1);

use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Exceptions\VillageContextMissing;

it('fails loudly when no village is set', function () {
    (new CurrentVillage)->id();
})->throws(VillageContextMissing::class);

it('returns the village that was set', function () {
    $current = new CurrentVillage;
    $current->set(7);

    expect($current->has())->toBeTrue()
        ->and($current->id())->toBe(7);
});

it('forgets the village after clear', function () {
    $current = new CurrentVillage;
    $current->set(7);
    $current->clear();

    expect($current->has())->toBeFalse();
    $current->id();
})->throws(VillageContextMissing::class);

it('is reset between scoped lifecycles (requests, jobs)', function () {
    app(CurrentVillage::class)->set(7);
    app()->forgetScopedInstances();

    expect(app(CurrentVillage::class)->has())->toBeFalse();
});
