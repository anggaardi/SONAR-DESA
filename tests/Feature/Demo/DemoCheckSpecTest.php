<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Report\Models\Report;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/** Desa Contoh with one published project at the ProjectFactory point (-8.5321, 115.3214) and one measurement. */
function checkSpecDemoTemplate(): void
{
    $template = Village::factory()->create(['slug' => config('sonar.demo.template_slug'), 'name' => 'Desa Contoh']);
    $desa = User::factory()->desa($template)->create();
    User::factory()->bpd($template)->create();
    $audit = Audit::factory()->create(['village_id' => $template->id, 'owner_id' => $desa->id]);
    $project = Project::factory()->create([
        'village_id' => $template->id,
        'audit_id' => $audit->id,
        'slug' => 'rabat-beton-jl-melati',
    ]);
    ProjectMeasurement::factory()->create(['project_id' => $project->id]);
}

/** Starts a demo as warga and returns the cloned project of this session. */
function startDemoAsWarga(): Project
{
    checkSpecDemoTemplate();
    test()->post('/demo/start/warga');
    $village = Village::query()->where('is_demo', true)->sole();

    return Project::withoutGlobalScope('village')->with('measurements')->where('village_id', $village->id)->sole();
}

it('gives a sample position inside the radius for a project of this demo session (M6-05)', function () {
    $project = startDemoAsWarga();

    $response = $this->getJson(route('demo.sample-location', $project->slug));

    $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect($response->json('accuracy_m'))->toBe(10)
        ->and((float) $response->json('lng'))->toBe(115.3214);
    // 20 m north of the project, well inside the 300 m radius.
    expect(((float) $response->json('lat') - -8.5321) * 111_320)->toEqualWithDelta(20.0, 0.5);
});

it('never gives a sample position for a real project (D-009)', function () {
    startDemoAsWarga();
    $real = Project::factory()->create(['slug' => 'proyek-asli']);

    $this->getJson(route('demo.sample-location', $real->slug))->assertNotFound();
});

it("never gives a sample position for another session's demo project", function () {
    $other = startDemoAsWarga();
    $this->flushSession();
    auth()->logout();
    $this->post('/demo/start/warga');

    $this->getJson(route('demo.sample-location', $other->slug))->assertNotFound();
});

it('lets a demo warga open Cek Spek and send a report with the sample, without Google (C12)', function () {
    Storage::fake('local');
    $project = startDemoAsWarga();
    $sample = $this->getJson(route('demo.sample-location', $project->slug))->json();

    $this->get(route('reports.check', $project->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('report/Check')->where('demo.role', 'warga'));

    $this->post(route('reports.store', $project->slug), [
        'answers' => [['measurement_id' => $project->measurements->sole()->id, 'answer' => 'mismatch', 'measured_value' => '8']],
        'photos' => [UploadedFile::fake()->image('contoh.jpg', 800, 600)],
        'lat' => $sample['lat'],
        'lng' => $sample['lng'],
        'accuracy_m' => $sample['accuracy_m'],
    ])->assertSessionHasNoErrors()->assertRedirect(route('reports.submitted', $project->slug));

    expect(Report::query()->where('project_id', $project->id)->sole()->user_id)->toBe(auth()->id());
});
