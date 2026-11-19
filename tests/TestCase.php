<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests assert Inertia props, not assets. Without this, rendering app.blade.php needs
        // public/build/manifest.json or public/hot, so a fresh checkout (CI) fails (M0-04).
        $this->withoutVite();
    }
}
