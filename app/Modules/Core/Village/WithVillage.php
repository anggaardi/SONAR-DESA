<?php

declare(strict_types=1);

namespace App\Modules\Core\Village;

use Closure;

/**
 * Job middleware: sets CurrentVillage for the job and always clears it afterwards.
 * Queue workers are long-running and have no session (PRD Arsitektur 6.7 callout).
 *
 * Usage in a job: public function middleware(): array { return [new WithVillage($this->villageId)]; }
 */
final readonly class WithVillage
{
    public function __construct(private int $villageId) {}

    public function handle(object $job, Closure $next): mixed
    {
        $current = app(CurrentVillage::class);
        $current->set($this->villageId);

        try {
            return $next($job);
        } finally {
            $current->clear();
        }
    }
}
