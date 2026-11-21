<?php

declare(strict_types=1);

use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use Inertia\Testing\AssertableInertia;

function publishedProject(array $attributes = []): Project
{
    return Project::factory()->create(['slug' => 'rabat-beton-melati', ...$attributes]);
}

it('sends a guest to Google and remembers the checklist URL', function () {
    publishedProject();

    $response = $this->get(route('reports.check', 'rabat-beton-melati'));

    $response->assertRedirect(route('auth.google'))
        ->assertSessionHas('url.intended', route('reports.check', 'rabat-beton-melati'));
});

it('shows every measurement with its RAB value and how to measure it (C4)', function () {
    $project = publishedProject(['name' => 'Rabat beton Jl. Melati']);
    ProjectMeasurement::factory()->create([
        'project_id' => $project->id, 'name' => 'Tebal beton', 'value' => '12.000', 'unit' => 'cm', 'how_to' => 'Ukur di tepi jalan',
    ]);
    ProjectMeasurement::factory()->create([
        'project_id' => $project->id, 'name' => 'Panjang', 'value' => '200.000', 'unit' => 'm', 'how_to' => 'Langkah kaki',
    ]);

    $response = $this->actingAs(User::factory()->create())->get(route('reports.check', 'rabat-beton-melati'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('report/Check')
        ->where('checkSpec.project.name', 'Rabat beton Jl. Melati')
        ->has('checkSpec.measurements', 2)
        ->where('checkSpec.measurements.0.name', 'Tebal beton')
        ->where('checkSpec.measurements.0.value', '12')
        ->where('checkSpec.measurements.0.unit', 'cm')
        ->where('checkSpec.measurements.0.how_to', 'Ukur di tepi jalan')
        ->where('checkSpec.limits.max_accuracy_m', 100)
        ->where('checkSpec.limits.max_photos', 3)
        ->where('checkSpec.limits.max_note_length', 280)
        ->where('checkSpec.urls.distance', route('reports.distance', 'rabat-beton-melati')));
});

it('never sends the project coordinates to the page', function () {
    publishedProject();

    $response = $this->actingAs(User::factory()->create())->get(route('reports.check', 'rabat-beton-melati'));

    $response->assertOk();
    expect($response->getContent())->not->toContain('8.5321')->not->toContain('115.3214');
});

it('forbids desa and BPD accounts from opening Cek Spek', function (string $role) {
    $project = publishedProject();
    $official = User::factory()->{$role}()->create(['village_id' => $project->village_id]);

    $this->actingAs($official)->get(route('reports.check', 'rabat-beton-melati'))->assertForbidden();
})->with(['desa', 'bpd']);

it('shows the not found page for a project that is not public', function (Closure $makeProject) {
    $makeProject();

    $response = $this->actingAs(User::factory()->create())->get(route('reports.check', 'rabat-beton-melati'));

    $response->assertNotFound()->assertInertia(fn (AssertableInertia $page) => $page->component('project/NotFound'));
})->with([
    'unknown' => [fn () => null],
    'not published' => [fn () => publishedProject(['published_at' => null])],
    "another visitor's demo" => [fn () => publishedProject(['village_id' => Village::factory()->create(['is_demo' => true])->id])],
]);
