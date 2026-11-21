<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

it('renders the Inertia home page with shared props', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('core/Home')
            ->where('auth.user', null)
            ->has('app.commit'));
});

it('renders the Inertia demo page', function () {
    $this->get('/demo')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('demo/Index')
            ->has('app.commit'));
});

it('answers the health check', function () {
    $this->get('/up')->assertOk();
});
