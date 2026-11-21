<?php

declare(strict_types=1);

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;

function googleAccount(string $id = 'google-123', string $email = 'ayu@gmail.com'): GoogleUser
{
    return GoogleUser::fake(['id' => $id, 'name' => 'Ni Made Ayu', 'email' => $email]);
}

it('sends the visitor to Google', function () {
    config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'client-secret']);
    Socialite::fake('google');

    $this->get(route('auth.google'))->assertRedirect('https://socialite.fake/google/authorize');
});

it('shows a retry page instead of an error when Google is not configured', function (?string $clientId, ?string $clientSecret) {
    config(['services.google.client_id' => $clientId, 'services.google.client_secret' => $clientSecret]);

    $response = $this->get(route('auth.google'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('report/GoogleSignInFailed')
        ->where('reason', 'failed')
        ->where('retryUrl', route('auth.google')));
})->with([
    'no client id' => [null, 'client-secret'],
    'empty client id' => ['', 'client-secret'],
    'no client secret' => ['client-id', null],
]);

it('creates a warga account and returns to the checklist the visitor came from', function () {
    Socialite::fake('google', googleAccount());

    $response = $this->withSession(['url.intended' => '/p/rabat-beton-melati/cek'])
        ->get(route('auth.google.callback'));

    $response->assertRedirect('/p/rabat-beton-melati/cek');
    $user = User::query()->sole();
    $this->assertAuthenticatedAs($user);
    expect($user)
        ->role->toBe(Role::Warga)
        ->village_id->toBeNull()
        ->google_id->toBe('google-123')
        ->email->toBe('ayu@gmail.com');
});

it('does not keep the Google display name', function () {
    Socialite::fake('google', googleAccount());

    $this->get(route('auth.google.callback'));

    expect(User::query()->sole()->name)->not->toContain('Ayu');
});

it('signs a returning warga into the same account', function () {
    $warga = User::factory()->create(['google_id' => 'google-123', 'email' => 'ayu@gmail.com']);
    Socialite::fake('google', googleAccount());

    $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($warga);
    $this->assertDatabaseCount('users', 1);
});

it('refuses a Google account whose email belongs to a desa or BPD account', function (string $role) {
    $official = User::factory()->{$role}()->create(['email' => 'kantor@desa.id']);
    Socialite::fake('google', googleAccount(email: 'kantor@desa.id'));

    $response = $this->get(route('auth.google.callback'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('report/GoogleSignInFailed')
        ->where('reason', 'not_allowed'));
    $this->assertGuest();
    expect($official->fresh()->google_id)->toBeNull();
    $this->assertDatabaseCount('users', 1);
})->with(['desa', 'bpd']);

it('refuses a Google id that is linked to a desa account', function () {
    User::factory()->desa()->create(['google_id' => 'google-123']);
    Socialite::fake('google', googleAccount());

    $this->get(route('auth.google.callback'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('reason', 'not_allowed'));

    $this->assertGuest();
});

it('keeps the checklist URL when the visitor cancels on Google', function () {
    $response = $this->withSession(['url.intended' => '/p/rabat-beton-melati/cek'])
        ->get(route('auth.google.callback', ['error' => 'access_denied']));

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('report/GoogleSignInFailed')
            ->where('reason', 'cancelled'))
        ->assertSessionHas('url.intended', '/p/rabat-beton-melati/cek');
    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

it('shows the retry page when Google cannot be reached or the state does not match', function () {
    Socialite::fake('google', fn () => throw new InvalidStateException);

    $response = $this->get(route('auth.google.callback'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('report/GoogleSignInFailed')
        ->where('reason', 'failed'));
    $this->assertGuest();
});
