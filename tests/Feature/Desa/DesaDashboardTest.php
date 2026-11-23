<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Models\Report;
use Inertia\Testing\AssertableInertia;

it('redirects guests attempting to access the desa dashboard to login', function () {
    $this->get('/desa')
        ->assertRedirect(route('login'));
});

it('forbids warga and bpd from accessing the desa dashboard', function (Role $role) {
    $village = Village::factory()->create();
    $user = User::factory()->create([
        'role' => $role,
        'village_id' => $role->belongsToVillage() ? $village->id : null,
    ]);

    $this->actingAs($user)
        ->get('/desa')
        ->assertForbidden();
})->with([Role::Warga, Role::Bpd]);

it('renders the dashboard with village data and statistics for desa users', function () {
    $village = Village::factory()->create(['name' => 'Desa Melinggih', 'slug' => 'desa-melinggih']);
    $desa = User::factory()->desa($village)->create();

    $audit = Audit::factory()->create([
        'village_id' => $village->id,
        'owner_id' => $desa->id,
        'status' => AuditStatus::NeedsReview,
    ]);

    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
        'name' => 'Betonisasi Jalan',
    ]);

    Report::factory()->create([
        'project_id' => $project->id,
        'invalid_reason' => null,
    ]);

    $this->actingAs($desa)
        ->get('/desa')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('desa/Dashboard')
            ->has('village', fn (AssertableInertia $v) => $v
                ->where('name', 'Desa Melinggih')
                ->where('slug', 'desa-melinggih')
                ->etc()
            )
            ->has('stats', fn (AssertableInertia $s) => $s
                ->where('total_projects', 1)
                ->where('pending_reports', 1)
                ->where('audits_needs_review', 1)
                ->etc()
            )
            ->has('projects', 1)
        );
});

it('redirects /desa/dashboard to /desa and /audits to /audits/create when no audit exists', function () {
    $village = Village::factory()->create();
    $desa = User::factory()->desa($village)->create();

    $this->actingAs($desa)
        ->get('/desa/dashboard')
        ->assertRedirect(route('desa.dashboard'));

    $this->actingAs($desa)
        ->get('/audits')
        ->assertRedirect(route('audits.create'));
});

it('lands on desa dashboard when starting demo as desa', function () {
    Village::factory()->create(['slug' => 'desa-contoh-template', 'name' => 'Desa Contoh']);

    $response = $this->post('/demo/start/desa');

    $response->assertRedirect(route('desa.dashboard'));
    expect(auth()->user()?->role)->toBe(Role::Desa);
});
