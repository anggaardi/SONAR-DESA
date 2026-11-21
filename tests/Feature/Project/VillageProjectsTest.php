<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Models\Report;
use Inertia\Testing\AssertableInertia;

it('renders published projects of a village on GET /d/{village}', function () {
    $village = Village::factory()->create([
        'name' => 'Desa Beraban',
        'slug' => 'desa-beraban',
        'regency' => 'Gianyar',
    ]);

    $audit1 = Audit::factory()->create(['village_id' => $village->id]);
    $project1 = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit1->id,
        'name' => 'Proyek Terbit 1',
        'slug' => 'proyek-terbit-1',
        'published_at' => now(),
    ]);

    $audit2 = Audit::factory()->create(['village_id' => $village->id]);
    $project2 = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit2->id,
        'name' => 'Proyek Terbit 2',
        'slug' => 'proyek-terbit-2',
        'published_at' => now()->addHour(),
    ]);

    $audit3 = Audit::factory()->create(['village_id' => $village->id]);
    Project::factory()->unpublished()->create([
        'village_id' => $village->id,
        'audit_id' => $audit3->id,
        'name' => 'Proyek Draf',
        'slug' => 'proyek-draf',
    ]);

    $this->get('/d/desa-beraban')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('project/Village')
            ->where('village.name', 'Desa Beraban')
            ->where('village.slug', 'desa-beraban')
            ->where('village.regency', 'Gianyar')
            ->has('projects', 2)
            ->where('projects.0.slug', 'proyek-terbit-2')
            ->where('projects.1.slug', 'proyek-terbit-1'));
});

it('returns 404 for nonexistent village on GET /d/{village}', function () {
    $this->get('/d/desa-tidak-ada')->assertNotFound();
});

it('counts each citizen with a valid in-radius report once on GET /d/{village}', function () {
    $village = Village::factory()->create(['slug' => 'desa-beraban']);
    $first = Project::factory()->create(['village_id' => $village->id]);
    $second = Project::factory()->create(['village_id' => $village->id]);
    $citizen = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);

    Report::factory()->create(['project_id' => $first->id, 'user_id' => $citizen->id]);
    Report::factory()->create(['project_id' => $second->id, 'user_id' => $citizen->id]);
    Report::factory()->create(['project_id' => $first->id]);
    Report::factory()->create(['project_id' => $second->id, 'invalid_reason' => 'Foto tidak jelas']);

    $otherVillageProject = Project::factory()->create(['village_id' => Village::factory()->create()->id]);
    Report::factory()->create(['project_id' => $otherVillageProject->id]);

    $this->get('/d/desa-beraban')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('citizen_reports_count', 2));
});
