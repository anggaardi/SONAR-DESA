<?php

declare(strict_types=1);

use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Report\Models\Report;
use App\Modules\Report\Models\ReportAnswer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => Storage::fake('local'));

it('seeds Desa Contoh with its files and template accounts that cannot log in', function () {
    $this->seed(DatabaseSeeder::class);

    $template = Village::query()->where('slug', config('sonar.demo.template_slug'))->sole();
    expect(Project::query()->withoutGlobalScopes()->where('village_id', $template->id)->count())->toBe(4);
    Storage::disk('local')->assertExists([
        'progress/sample-progress-0.webp',
        'progress/sample-progress-65.webp',
        'audits/sample-rab-melati.pdf',
        'audits/sample-rab-lampu-jalan.pdf',
    ]);

    $this->post('/login', ['email' => 'desa@desa-contoh.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('gives the same rows when seeded twice', function () {
    $counts = fn (): array => [
        User::query()->count(),
        Project::query()->withoutGlobalScopes()->count(),
        Report::query()->withoutGlobalScopes()->count(),
    ];

    $this->seed(DatabaseSeeder::class);
    $first = $counts();
    $this->seed(DatabaseSeeder::class);

    expect($counts())->toBe($first);
});

it('gives every new demo the seeded Desa Contoh projects', function () {
    $this->seed(DatabaseSeeder::class);

    $response = $this->post('/demo/start/warga');

    $demo = Village::query()->where('is_demo', true)->sole();
    $response->assertRedirect(route('villages.projects', $demo->slug));
    $this->get("/d/{$demo->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('projects', 4));
});

it('seeds Rabat Beton thickness and mismatch answers in cm, not meters', function () {
    $this->seed(DatabaseSeeder::class);

    $template = Village::query()->where('slug', config('sonar.demo.template_slug'))->sole();
    $project = Project::query()->withoutGlobalScopes()
        ->where('village_id', $template->id)
        ->where('slug', 'rabat-beton-jl-melati-rt-02-03')
        ->sole();

    $thickness = ProjectMeasurement::query()
        ->where('project_id', $project->id)
        ->where('name', 'Tebal')
        ->sole();

    expect((float) $thickness->value)->toBe(12.0)
        ->and($thickness->unit)->toBe('cm');

    $answers = ReportAnswer::query()
        ->where('measurement_id', $thickness->id)
        ->whereNotNull('measured_value')
        ->orderBy('measured_value')
        ->get(['measured_value', 'measured_unit']);

    expect($answers)->toHaveCount(2)
        ->and((float) $answers[0]->measured_value)->toBe(8.0)
        ->and($answers[0]->measured_unit)->toBe('cm')
        ->and((float) $answers[1]->measured_value)->toBe(9.0)
        ->and($answers[1]->measured_unit)->toBe('cm');
});
