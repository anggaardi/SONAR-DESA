<?php

declare(strict_types=1);

use App\Modules\Core\Village\Village;
use App\Modules\Project\Models\Project;

// Project point is -8.5321, 115.3214 (ProjectFactory). 0.001 degree of latitude is about 111.2 m.
function projectAtDefaultPoint(array $attributes = []): Project
{
    return Project::factory()->create(['slug' => 'rabat-beton-melati', ...$attributes]);
}

function visitorNorthOfProject(float $latitudeOffset): array
{
    return ['lat' => -8.5321 + $latitudeOffset, 'lng' => 115.3214];
}

it('tells a visitor at the project point that they are inside the radius, without signing in', function () {
    projectAtDefaultPoint();

    $response = $this->postJson(route('reports.distance', 'rabat-beton-melati'), visitorNorthOfProject(0.0));

    $response->assertOk()->assertExactJson(['within_radius' => true, 'distance_m' => 0]);
});

it('reports a rounded-up distance inside the radius', function () {
    projectAtDefaultPoint();

    // 0.0025 degree is about 278 m.
    $response = $this->postJson(route('reports.distance', 'rabat-beton-melati'), visitorNorthOfProject(0.0025));

    $response->assertOk()->assertJsonPath('within_radius', true);
    expect($response->json('distance_m'))->toBeGreaterThanOrEqual(278)->toBeLessThanOrEqual(288);
});

it('marks a visitor outside the radius but still near', function () {
    projectAtDefaultPoint();

    // 0.0045 degree is about 500 m.
    $response = $this->postJson(route('reports.distance', 'rabat-beton-melati'), visitorNorthOfProject(0.0045));

    $response->assertOk()->assertJsonPath('within_radius', false);
    expect($response->json('distance_m'))->toBeGreaterThanOrEqual(500)->toBeLessThanOrEqual(510);
});

it('hides the distance when the visitor is far away', function () {
    projectAtDefaultPoint();

    $response = $this->postJson(route('reports.distance', 'rabat-beton-melati'), visitorNorthOfProject(0.05));

    $response->assertOk()->assertExactJson(['within_radius' => false, 'distance_m' => null]);
});

it('never exposes the project coordinates and forbids caching', function () {
    projectAtDefaultPoint();

    $response = $this->postJson(route('reports.distance', 'rabat-beton-melati'), visitorNorthOfProject(0.001));

    $response->assertOk()
        ->assertJsonMissingPath('lat')
        ->assertJsonMissingPath('lng')
        ->assertHeader('Cache-Control', 'no-store, private');
    expect($response->getContent())->not->toContain('8.5321')->not->toContain('115.3214');
});

it('returns 404 for an unknown project', function () {
    $this->postJson(route('reports.distance', 'tidak-ada'), visitorNorthOfProject(0.0))->assertNotFound();
});

it('returns 404 for a project that is not published', function () {
    projectAtDefaultPoint(['published_at' => null]);

    $this->postJson(route('reports.distance', 'rabat-beton-melati'), visitorNorthOfProject(0.0))->assertNotFound();
});

it('returns 404 for a project without a location', function () {
    projectAtDefaultPoint(['lat' => null, 'lng' => null]);

    $this->postJson(route('reports.distance', 'rabat-beton-melati'), visitorNorthOfProject(0.0))->assertNotFound();
});

it("returns 404 for another visitor's demo project", function () {
    $demoVillage = Village::factory()->create(['is_demo' => true]);
    projectAtDefaultPoint(['village_id' => $demoVillage->id]);

    $this->postJson(route('reports.distance', 'rabat-beton-melati'), visitorNorthOfProject(0.0))->assertNotFound();
});

it('rejects coordinates that are missing or out of range with 422', function (array $payload) {
    projectAtDefaultPoint();

    $this->postJson(route('reports.distance', 'rabat-beton-melati'), $payload)->assertUnprocessable();
})->with([
    'no coordinates' => [[]],
    'latitude missing' => [['lng' => 115.3]],
    'latitude over 90' => [['lat' => 91, 'lng' => 115.3]],
    'longitude over 180' => [['lat' => -8.5, 'lng' => 181]],
    'not a number' => [['lat' => 'abc', 'lng' => 115.3]],
]);

it('returns 429 once a visitor sends too many requests', function () {
    config(['sonar.report.distance_hints_per_minute' => 2]);
    projectAtDefaultPoint();
    $url = route('reports.distance', 'rabat-beton-melati');

    $this->postJson($url, visitorNorthOfProject(0.0))->assertOk();
    $this->postJson($url, visitorNorthOfProject(0.0))->assertOk();

    $this->postJson($url, visitorNorthOfProject(0.0))->assertStatus(429);
});
