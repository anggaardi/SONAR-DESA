<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
 * Feature tests run on the real Postgres "testing" database (A18), not SQLite.
 * No outbound HTTP in any test (PRD Arsitektur 9.6).
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');
