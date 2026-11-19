<?php

declare(strict_types=1);

namespace App\Modules\Demo\Console;

use App\Modules\Core\Village\Village;
use App\Modules\Demo\Actions\DemoSession;
use Illuminate\Console\Command;

/**
 * Hourly (routes/console.php): removes demo villages unused for sonar.demo.ttl_hours (M6-04, F6-3).
 * Idempotent; the Desa Contoh template is never a demo village, so it is never touched.
 */
final class PurgeExpiredDemos extends Command
{
    protected $signature = 'demo:purge-expired';

    protected $description = 'Delete expired demo villages and their files';

    public function handle(): int
    {
        $count = 0;

        Village::query()
            ->where('is_demo', true)
            ->where('demo_expires_at', '<', now())
            ->each(function (Village $village) use (&$count): void {
                DemoSession::destroy($village);
                $count++;
            });

        $this->info("Purged {$count} demo villages.");

        return self::SUCCESS;
    }
}
