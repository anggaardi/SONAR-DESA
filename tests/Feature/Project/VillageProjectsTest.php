<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Models\Project;
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
