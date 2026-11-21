<?php

declare(strict_types=1);

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Http\Middleware\SetCurrentVillage;
use App\Modules\Core\Village\Village;
use Inertia\Testing\AssertableInertia;

function villageAccount(Role $role): User
{
    return User::factory()->create([
        'role' => $role,
        'village_id' => Village::factory()->create()->id,
        'email' => "{$role->value}@desa.test",
        'password' => 'rahasia-benar',
    ]);
}

it('shows the login page to guests', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('core/Login'));
});

it('logs in desa and bpd accounts with the right password', function (Role $role) {
    $user = villageAccount($role);

    $this->post('/login', ['email' => $user->email, 'password' => 'rahasia-benar'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
})->with([Role::Desa, Role::Bpd]);

it('rejects a wrong password and a warga account with the same message', function () {
    $desa = villageAccount(Role::Desa);
    $warga = User::factory()->create(['role' => Role::Warga, 'village_id' => null, 'password' => 'rahasia-benar']);

    $wrongPassword = $this->post('/login', ['email' => $desa->email, 'password' => 'salah'])
        ->assertSessionHasErrors('email');
    $wargaAttempt = $this->post('/login', ['email' => $warga->email, 'password' => 'rahasia-benar'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))
        ->toBe('Email atau kata sandi tidak cocok. Periksa lagi, atau hubungi tim Sonar Desa bila lupa.');
    $this->assertGuest();
    expect($wrongPassword->status())->toBe($wargaAttempt->status());
});

it('blocks the sixth attempt within a minute (M0-07 throttle 5/min)', function () {
    $user = villageAccount(Role::Desa);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'salah']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'rahasia-benar'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toStartWith('Terlalu banyak percobaan masuk.');
    $this->assertGuest();
});

it('logs out and ends the session', function () {
    $user = villageAccount(Role::Bpd);

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

it('drops the demo village from the session when a real account logs in', function () {
    $user = villageAccount(Role::Desa);
    $demoVillage = Village::factory()->create(['is_demo' => true]);

    $this->withSession([
        SetCurrentVillage::DEMO_SESSION_KEY => $demoVillage->id,
        SetCurrentVillage::DEMO_ROLE_SESSION_KEY => 'warga',
    ])->post('/login', ['email' => $user->email, 'password' => 'rahasia-benar'])
        ->assertRedirect(route('home'))
        ->assertSessionMissing(SetCurrentVillage::DEMO_SESSION_KEY)
        ->assertSessionMissing(SetCurrentVillage::DEMO_ROLE_SESSION_KEY);

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('demo', null));
});

it('lets a demo account open the login page to switch to a real account', function () {
    $demoVillage = Village::factory()->create(['is_demo' => true]);
    $demoAccount = User::factory()->desa($demoVillage)->create(['password' => null]);

    $this->actingAs($demoAccount)
        ->get('/login')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('core/Login'));
});

it('sends a logged-in real account away from the login page', function () {
    $this->actingAs(villageAccount(Role::Bpd))
        ->get('/login')
        ->assertRedirect(route('home'));
});

it('sends guests on protected routes to the login page', function () {
    $this->post('/logout')->assertRedirect(route('login'));
});
