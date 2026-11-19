<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Models\Project;
use Inertia\Testing\AssertableInertia;

it('generates SVG QR code poster for desa user (P9, M3-06)', function () {
    $village = Village::factory()->create([
        'name' => 'Desa Melati',
        'regency' => 'Gianyar',
    ]);

    $desaUser = User::factory()->create([
        'role' => Role::Desa,
        'village_id' => $village->id,
    ]);

    $audit = Audit::factory()->create(['village_id' => $village->id]);
    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
        'name' => 'Rabat beton Jl. Melati',
        'slug' => 'rabat-beton-melati',
        'published_at' => now(),
    ]);

    $response = $this->actingAs($desaUser)
        ->get("/projects/{$project->id}/qr")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('project/QrPoster')
            ->where('project.name', 'Rabat beton Jl. Melati')
            ->where('project.slug', 'rabat-beton-melati')
            ->where('project.short_url', url('/p/rabat-beton-melati'))
            ->where('cta_text', 'Scan untuk lihat rincian anggaran')
            ->has('qr_svg'));

    $svg = $response->viewData('page')['props']['qr_svg'];
    expect($svg)->toContain('<svg');
    expect($svg)->toContain('</svg>');
});

it('prevents another village desa from viewing project QR (C1, IDOR)', function () {
    $villageA = Village::factory()->create();
    $villageB = Village::factory()->create();

    $desaUserB = User::factory()->create([
        'role' => Role::Desa,
        'village_id' => $villageB->id,
    ]);

    $auditA = Audit::factory()->create(['village_id' => $villageA->id]);
    $projectA = Project::factory()->create([
        'village_id' => $villageA->id,
        'audit_id' => $auditA->id,
    ]);

    $this->actingAs($desaUserB)
        ->get("/projects/{$projectA->id}/qr")
        ->assertNotFound();
});

it('prevents warga from viewing project QR', function () {
    $village = Village::factory()->create();
    $warga = User::factory()->create([
        'role' => Role::Warga,
        'village_id' => null,
    ]);

    $audit = Audit::factory()->create(['village_id' => $village->id]);
    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
    ]);

    $this->actingAs($warga)
        ->get("/projects/{$project->id}/qr")
        ->assertForbidden();
});
