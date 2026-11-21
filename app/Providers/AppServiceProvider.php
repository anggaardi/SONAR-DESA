<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Report\Domain\ReportClassifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // Limits live in config/sonar.php (PRD Fitur QF3), so the pure Domain class gets them injected.
        $this->app->bind(ReportClassifier::class, static fn (): ReportClassifier => new ReportClassifier(
            radiusMeters: (int) config('sonar.report.radius_m'),
            maxAccuracyMeters: (int) config('sonar.report.max_accuracy_m'),
        ));
    }

    public function boot(): void
    {
        // Lazy loading hides N+1 queries; strict mode turns them into errors outside production.
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->assertRequiredEnv();

        RateLimiter::for('report-distance', static fn (Request $request): Limit => Limit::perMinute(
            (int) config('sonar.report.distance_hints_per_minute'),
        )->by((string) $request->ip()));
    }

    /**
     * Fail fast at boot with the list of missing keys (pattern from margi validateEnv).
     *
     * key:generate creates the key, and package:discover runs inside `docker build`
     * (composer dump-autoload), before any production secret exists.
     */
    private function assertRequiredEnv(): void
    {
        if (! $this->app->isProduction() || $this->app->runningConsoleCommand('key:generate', 'package:discover')) {
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
