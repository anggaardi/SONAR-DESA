<?php

declare(strict_types=1);

use App\Modules\Demo\Console\PurgeExpiredDemos;
use Illuminate\Support\Facades\Schedule;

// M6-04, F6-3: demo villages unused for 24 hours are deleted every hour.
Schedule::command(PurgeExpiredDemos::class)->hourly()->withoutOverlapping();
