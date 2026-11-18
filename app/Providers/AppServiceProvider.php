<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Core\Village\CurrentVillage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class AppServiceProvider extends ServiceProvider
{
    /** Keys that must be set outside local/testing. Values are never printed. */
    private const REQUIRED_IN_PRODUCTION = ['APP_KEY', 'APP_URL', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];

    public function register(): void
    {
        // Scoped: reset per request and per queue job (PRD Arsitektur 6.7).
        $this->app->scoped(CurrentVillage::class);
    }

    public function boot(): void
    {
        // Lazy loading hides N+1 queries; strict mode turns them into errors outside production.
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->assertRequiredEnv();
    }

    /**
     * Fail fast at boot with the list of missing keys (pattern from margi validateEnv).
     */
    private function assertRequiredEnv(): void
    {
        if (! $this->app->isProduction() || $this->app->runningConsoleCommand('key:generate')) {
            return;
        }

        $missing = array_values(array_filter(
            self::REQUIRED_IN_PRODUCTION,
            static fn (string $key): bool => blank(env($key)),
        ));

        if ($missing !== []) {
            throw new RuntimeException('Missing environment variables: '.implode(', ', $missing));
        }
    }
}
