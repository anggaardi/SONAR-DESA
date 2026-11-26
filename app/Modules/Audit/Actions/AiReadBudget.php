<?php

declare(strict_types=1);

namespace App\Modules\Audit\Actions;

use App\Modules\Core\Village\Village;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Enforces daily global and demo AI Vision read budgets in WITA timezone (PRD Arsitektur 6.6, D19).
 *
 * Delegates demo identification to Village::isDemoId() so Audit stays decoupled from demo flags (F6-4).
 */
final class AiReadBudget
{
    public function todayDate(): string
    {
        return CarbonImmutable::now((string) config('sonar.display_timezone', 'Asia/Makassar'))
            ->toDateString();
    }

    /**
     * Atomically reserves one AI read slot for the given village, or returns false if daily budget is exhausted.
     */
    public function claimForVillage(int $villageId): bool
    {
        $dailyLimit = max(0, (int) config('sonar.ai_budget.daily_reads', 150));
        if ($dailyLimit <= 0) {
            return false;
        }

        $isDemo = Village::isDemoId($villageId);
        $demoLimit = max(0, (int) config('sonar.ai_budget.demo_daily_reads', 60));
        if ($isDemo && $demoLimit <= 0) {
            return false;
        }

        $today = $this->todayDate();
        $now = CarbonImmutable::now();

        DB::table('ai_usage')->insertOrIgnore([
            'usage_date' => $today,
            'reads_count' => 0,
            'demo_reads_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $query = DB::table('ai_usage')
            ->where('usage_date', $today)
            ->where('reads_count', '<', $dailyLimit);

        if ($isDemo) {
            $affected = $query
                ->where('demo_reads_count', '<', $demoLimit)
                ->update([
                    'reads_count' => DB::raw('reads_count + 1'),
                    'demo_reads_count' => DB::raw('demo_reads_count + 1'),
                    'updated_at' => $now,
                ]);
        } else {
            $affected = $query->update([
                'reads_count' => DB::raw('reads_count + 1'),
                'updated_at' => $now,
            ]);
        }

        return $affected > 0;
    }

    /**
     * Returns current daily AI budget telemetry for a village without mutating counters.
     *
     * @return array{
     *     date: string,
     *     daily_limit: int,
     *     daily_used: int,
     *     daily_remaining: int,
     *     demo_limit: int,
     *     demo_used: int,
     *     demo_remaining: int,
     *     is_exhausted: bool
     * }
     */
    public function statusForVillage(int $villageId): array
    {
        $today = $this->todayDate();
        $row = DB::table('ai_usage')->where('usage_date', $today)->first();

        $dailyLimit = max(0, (int) config('sonar.ai_budget.daily_reads', 150));
        $demoLimit = max(0, (int) config('sonar.ai_budget.demo_daily_reads', 60));
        $dailyUsed = $row !== null ? (int) $row->reads_count : 0;
        $demoUsed = $row !== null ? (int) $row->demo_reads_count : 0;

        $dailyRemaining = max(0, $dailyLimit - $dailyUsed);
        $demoRemaining = max(0, $demoLimit - $demoUsed);
        $isDemo = Village::isDemoId($villageId);

        $isExhausted = $dailyRemaining === 0 || ($isDemo && $demoRemaining === 0);

        return [
            'date' => $today,
            'daily_limit' => $dailyLimit,
            'daily_used' => $dailyUsed,
            'daily_remaining' => $dailyRemaining,
            'demo_limit' => $demoLimit,
            'demo_used' => $demoUsed,
            'demo_remaining' => $demoRemaining,
            'is_exhausted' => $isExhausted,
        ];
    }
}
