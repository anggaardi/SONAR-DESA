<?php

declare(strict_types=1);

use App\Modules\Core\Auth\User;
use App\Modules\Core\Http\Middleware\SetCurrentVillage;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Exceptions\VillageContextMissing;
use App\Modules\Core\Village\OwnedThroughParent;
use App\Modules\Core\Village\Village;
use App\Modules\Core\Village\VillageOwnership;
use App\Modules\Core\Village\WithVillage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Fixtures\ScopedThing;
use Tests\Fixtures\ScopedThingChild;

// Test-only tables standing in for real (V) tables and child tables until M2+ exist.
beforeEach(function () {
    Schema::create('scoped_things', function (Blueprint $table) {
        $table->id();
        $table->foreignId('village_id')->constrained();
        $table->string('name');
    });
    Schema::create('scoped_thing_children', function (Blueprint $table) {
        $table->id();
        $table->foreignId('scoped_thing_id')->constrained();
    });

    $this->a = Village::factory()->create();
    $this->b = Village::factory()->create();
    $this->current = app(CurrentVillage::class);
});

function thingIn(Village $village, string $name): ScopedThing
{
    $current = app(CurrentVillage::class);
    $current->set($village->id);
    $thing = ScopedThing::create(['name' => $name]);
    $current->clear();

    return $thing;
}

it('only returns rows of the current village', function () {
    thingIn($this->a, 'a-1');
    thingIn($this->b, 'b-1');

    $this->current->set($this->a->id);

    expect(ScopedThing::pluck('name')->all())->toBe(['a-1']);
});

it('fills village_id from the current village on create', function () {
    $thing = thingIn($this->a, 'a-1');

    expect($thing->village_id)->toBe($this->a->id);
});

it('throws instead of returning all villages when context is missing', function () {
    thingIn($this->a, 'a-1');

    ScopedThing::count();
})->throws(VillageContextMissing::class);

it('does not find another village record by id (F0-5)', function () {
    $other = thingIn($this->b, 'b-1');
    $this->current->set($this->a->id);

    expect(ScopedThing::find($other->id))->toBeNull();
});

it('returns 404 for a child record of another village (C1, F0-8)', function () {
    $other = thingIn($this->b, 'b-1');
    $child = ScopedThingChild::create(['scoped_thing_id' => $other->id]);
    $this->current->set($this->a->id);

    app(VillageOwnership::class)->assert($child);
})->throws(NotFoundHttpException::class);

it('accepts a child record of the current village', function () {
    $own = thingIn($this->a, 'a-1');
    $child = ScopedThingChild::create(['scoped_thing_id' => $own->id]);
    $this->current->set($this->a->id);

    app(VillageOwnership::class)->assert($child);

    expect(true)->toBeTrue();
});

it('lets a warga without village log in: users have no global scope (C3, F0-6)', function () {
    $warga = User::factory()->create();

    expect(User::find($warga->id)?->id)->toBe($warga->id);
});

it('scopes a job to its village and clears it afterwards (F0-6)', function () {
    thingIn($this->a, 'a-1');
    thingIn($this->b, 'b-1');

    $names = (new WithVillage($this->a->id))->handle(new stdClass, fn () => ScopedThing::pluck('name')->all());

    expect($names)->toBe(['a-1'])
        ->and($this->current->has())->toBeFalse();
});

it('sets the village from the logged-in desa user through middleware', function () {
    thingIn($this->a, 'a-1');
    thingIn($this->b, 'b-1');
    Route::middleware(['web', 'village'])->get('/_test/things', fn () => ScopedThing::pluck('name'));

    $this->actingAs(User::factory()->desa($this->a)->create())
        ->get('/_test/things')
        ->assertOk()
        ->assertExactJson(['a-1']);
});

it('sets the village from the demo session through middleware', function () {
    thingIn($this->b, 'b-1');
    Route::middleware(['web', 'village'])->get('/_test/things', fn () => ScopedThing::pluck('name'));

    $this->withSession([SetCurrentVillage::DEMO_SESSION_KEY => $this->b->id])
        ->get('/_test/things')
        ->assertOk()
        ->assertExactJson(['b-1']);
});

it('keeps child models on the OwnedThroughParent contract', function () {
    expect(new ScopedThingChild)->toBeInstanceOf(OwnedThroughParent::class);
});
