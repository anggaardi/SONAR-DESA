<?php

declare(strict_types=1);

namespace App\Modules\Demo\Http\Middleware;

use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Http\Middleware\SetCurrentVillage;
use App\Modules\Core\Village\Village;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Enforces the per-session/per-village AI RAB upload limit in demo mode (PRD Fitur 7.2, M6-05, AC M6).
 *
 * Lives inside Demo rather than Audit so feature modules stay unaware of demo mode (F6-4).
 * Sample RAB runs (`use_sample = true`) do not count toward the custom AI upload quota.
 */
final class EnforceDemoUploadLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->routeIs('audits.store', 'audits.replace_file', 'audits.retry')) {
            return $next($request);
        }

        if ($request->routeIs('audits.store') && $request->boolean('use_sample')) {
            return $next($request);
        }

        if ($request->routeIs('audits.retry') && ! $this->isCustomAiRetry($request)) {
            return $next($request);
        }

        $village = $this->resolveVillage($request);
        if ($village === null || ! $village->is_demo) {
            return $next($request);
        }

        $limit = (int) config('sonar.demo.upload_limit', 3);

        $claimed = $limit > 0
            ? Village::query()
                ->whereKey($village->id)
                ->where('demo_ai_uploads_count', '<', $limit)
                ->increment('demo_ai_uploads_count')
            : 0;

        if ($claimed === 0) {
            $message = 'Batas demo tercapai, coba contoh RAB.';

            return back()->withErrors(['file' => $message])->with('error', $message);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->releaseSlot($village->id);

            throw $exception;
        }

        $hasErrors = $request->session()->has('errors') || $request->session()->has('error');
        if ($hasErrors || $response->getStatusCode() >= 400) {
            $this->releaseSlot($village->id);
        }

        return $response;
    }

    private function resolveVillage(Request $request): ?Village
    {
        $user = $request->user();
        $villageId = $user instanceof User && $user->village_id !== null
            ? $user->village_id
            : $request->session()->get(SetCurrentVillage::DEMO_SESSION_KEY);

        if (! is_numeric($villageId)) {
            return null;
        }

        return Village::query()->find((int) $villageId);
    }

    private function isCustomAiRetry(Request $request): bool
    {
        $audit = $request->route('audit');
        if ($audit instanceof Audit) {
            return $audit->source === 'ocr_ai';
        }

        if (! is_numeric($audit)) {
            return false;
        }

        return Audit::withoutGlobalScopes()->whereKey((int) $audit)->value('source') === 'ocr_ai';
    }

    private function releaseSlot(int $villageId): void
    {
        Village::query()
            ->whereKey($villageId)
            ->where('demo_ai_uploads_count', '>', 0)
            ->decrement('demo_ai_uploads_count');
    }
}
