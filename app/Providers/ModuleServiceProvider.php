<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Loads each module's routes.php and migrations (PRD Arsitektur 6.1, 6.2).
 * Modules are plain folders under app/Modules; there is no module package (decision A8).
 */
final class ModuleServiceProvider extends ServiceProvider
{
    /** Order matters only for readability; modules do not depend on load order. */
    public const MODULES = ['Core', 'Standard', 'Audit', 'Project', 'Report', 'Review', 'Demo'];

    public function boot(): void
    {
        foreach (self::MODULES as $module) {
            $base = app_path("Modules/{$module}");

            if (is_file("{$base}/routes.php")) {
                Route::middleware('web')->group("{$base}/routes.php");
            }

            if (is_dir("{$base}/Database/Migrations")) {
                $this->loadMigrationsFrom("{$base}/Database/Migrations");
            }
        }

        // Artisan only discovers app/Console/Commands; module commands live in app/Modules/<Name>/Console.
        if ($this->app->runningInConsole()) {
            $this->commands($this->moduleCommands());
        }
    }

    /**
     * @return list<class-string>
     */
    private function moduleCommands(): array
    {
        $commands = [];

        foreach (self::MODULES as $module) {
            foreach (glob(app_path("Modules/{$module}/Console/*.php")) ?: [] as $file) {
                $commands[] = "App\\Modules\\{$module}\\Console\\".basename($file, '.php');
            }
        }

        return $commands;
    }
}
