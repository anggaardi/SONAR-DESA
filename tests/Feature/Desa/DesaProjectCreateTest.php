<?php

declare(strict_types=1);

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;

it('redirects guests attempting to access retired project create routes to login', function () {
    $this->get('/desa/projects/create')
        ->assertRedirect(route('login'));

    $postResponse = $this->post('/desa/projects', []);
    expect($postResponse->getStatusCode())->toBeIn([404, 405]);
});

it('forbids warga and bpd from accessing the legacy project create redirect route', function (Role $role) {
    $village = Village::factory()->create();
    $user = User::factory()->create([
        'role' => $role,
        'village_id' => $role->belongsToVillage() ? $village->id : null,
    ]);

    $this->actingAs($user)
        ->get('/desa/projects/create')
        ->assertForbidden();
})->with([Role::Warga, Role::Bpd]);

it('redirects legacy GET /desa/projects/create to /audits/create for desa users and retires POST /desa/projects (D8, D17)', function () {
    $village = Village::factory()->create(['name' => 'Desa Melinggih', 'slug' => 'desa-melinggih']);
    $desa = User::factory()->desa($village)->create();

    $this->actingAs($desa)
        ->get('/desa/projects/create')
        ->assertRedirect(route('audits.create'));

    $postResponse = $this->actingAs($desa)->post('/desa/projects', []);
    expect($postResponse->getStatusCode())->toBeIn([404, 405]);
});
