<?php

declare(strict_types=1);

use App\Modules\Core\Auth\User;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Report\Models\Report;
use App\Modules\Review\Models\ReviewCycle;
use Inertia\Testing\AssertableInertia;

const SUBMITTED_SLUG = 'rabat-beton-melati';

/** A published project with an open review cycle. */
function projectInCycle(array $attributes = []): Project
{
    $project = Project::factory()->create(['slug' => SUBMITTED_SLUG, ...$attributes]);
    $cycle = ReviewCycle::create(['project_id' => $project->id, 'opened_at' => now()]);
    $project->update(['current_cycle_id' => $cycle->id]);

    return $project;
}

/** A counted Selisih report in the project's current cycle (ReportFactory: in radius, not invalid). */
function validMismatch(Project $project, array $attributes = []): Report
{
    return Report::factory()->create([
        'project_id' => $project->id,
        'cycle_id' => $project->current_cycle_id,
        'kind' => ReportKind::Mismatch,
        ...$attributes,
    ]);
}

it('confirms the report with its kind and the server count of valid Selisih reporters (C9)', function () {
    $project = projectInCycle(['name' => 'Rabat beton Jl. Melati']);
    $warga = User::factory()->create();
    validMismatch($project, ['user_id' => $warga->id]);
    validMismatch($project);

    $response = $this->actingAs($warga)->get(route('reports.submitted', SUBMITTED_SLUG));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('report/Submitted')
        ->where('submitted.project.name', 'Rabat beton Jl. Melati')
        ->where('submitted.report.kind', 'mismatch')
        ->where('submitted.mismatch_count.current', 2)
        ->where('submitted.mismatch_count.threshold', 3)
        ->where('submitted.urls.project', route('projects.show', SUBMITTED_SLUG))
        ->where('submitted.urls.check', route('reports.check', SUBMITTED_SLUG)));
});

it('counts only valid, active Selisih reports of distinct accounts in the current cycle (F12)', function () {
    $project = projectInCycle();
    $warga = User::factory()->create();
    $oldCycle = ReviewCycle::create(['project_id' => $project->id, 'opened_at' => now()->subMonth(), 'closed_at' => now()->subWeek()]);
    validMismatch($project, ['user_id' => $warga->id]);
    validMismatch($project);

    validMismatch($project, ['invalid_reason' => 'Foto tidak relevan']);
    validMismatch($project, ['superseded_by' => 999]);
    validMismatch($project, ['cycle_id' => $oldCycle->id]);
    validMismatch($project, ['kind' => ReportKind::Match]);
    validMismatch(Project::factory()->create(), ['user_id' => $warga->id]);

    $response = $this->actingAs($warga)->get(route('reports.submitted', SUBMITTED_SLUG));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('submitted.mismatch_count.current', 2));
});

it('shows the newest active report of the account', function () {
    $project = projectInCycle();
    $warga = User::factory()->create();
    $newer = Report::factory()->create([
        'project_id' => $project->id, 'cycle_id' => $project->current_cycle_id, 'user_id' => $warga->id, 'kind' => ReportKind::Match,
    ]);
    validMismatch($project, ['user_id' => $warga->id, 'superseded_by' => $newer->id]);

    $response = $this->actingAs($warga)->get(route('reports.submitted', SUBMITTED_SLUG));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('submitted.report.kind', 'match'));
});

it('sends an account without a report in this cycle back to the checklist', function () {
    $project = projectInCycle();
    $warga = User::factory()->create();
    validMismatch($project);

    $this->actingAs($warga)->get(route('reports.submitted', SUBMITTED_SLUG))
        ->assertRedirect(route('reports.check', SUBMITTED_SLUG));
});

it('never sends ids, email, coordinates, or the note to the page', function () {
    $project = projectInCycle();
    $warga = User::factory()->create(['email' => 'pelapor@contoh.test']);
    validMismatch($project, ['user_id' => $warga->id, 'note' => 'Catatan pribadi pelapor']);

    $response = $this->actingAs($warga)->get(route('reports.submitted', SUBMITTED_SLUG));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->missing('submitted.report.id')
        ->missing('submitted.report.user_id'));
    expect($response->getContent())
        ->not->toContain('pelapor@contoh.test')
        ->not->toContain('Catatan pribadi pelapor')
        ->not->toContain('8.5321')
        ->not->toContain('115.3214');
});

it('sends a guest to Google', function () {
    projectInCycle();

    $this->get(route('reports.submitted', SUBMITTED_SLUG))->assertRedirect(route('auth.google'));
});

it('forbids a desa account', function () {
    $project = projectInCycle();
    $official = User::factory()->desa()->create(['village_id' => $project->village_id]);

    $this->actingAs($official)->get(route('reports.submitted', SUBMITTED_SLUG))->assertForbidden();
});

it('shows the not found page for a project that is not published', function () {
    projectInCycle(['published_at' => null]);

    $response = $this->actingAs(User::factory()->create())->get(route('reports.submitted', SUBMITTED_SLUG));

    $response->assertNotFound()->assertInertia(fn (AssertableInertia $page) => $page->component('project/NotFound'));
});
