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
    /**
     * Env name => config key. Read through config() so the check survives `config:cache`.
     *
     * @var array<string, string>
     */
    private const REQUIRED_IN_PRODUCTION = [
        'APP_KEY' => 'app.key',
        'APP_URL' => 'app.url',
        'DB_HOST' => 'database.connections.pgsql.host',
        'DB_DATABASE' => 'database.connections.pgsql.database',
        'DB_USERNAME' => 'database.connections.pgsql.username',
        'DB_PASSWORD' => 'database.connections.pgsql.password',
    ];

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

        $missing = array_keys(array_filter(
            self::REQUIRED_IN_PRODUCTION,
            static fn (string $configKey): bool => blank(config($configKey)),
        ));

        if ($missing !== []) {
            throw new RuntimeException('Missing environment variables: '.implode(', ', $missing));
        }
    }
}
