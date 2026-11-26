<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Shared Inertia props for every page. Keep this small: it is sent on every visit,
 * and public pages have a 150 KB JS budget plus a 3 s target (A17, PRD Fitur P1).
 * Never share reporter data here (PRD Arsitektur 9.2, privacy).
 */
final class HandleInertiaRequests extends Middleware
{
    /** @var string */
    protected $rootView = 'app';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role->value,
                ],
            ],
            // PRD Fitur 7.2: every demo screen shows the banner with "Ganti peran" and "Mulai ulang demo".
            'demo' => $this->demoContext($request),
            // F0-4: commit shown in the footer to confirm which build is live.
            'app' => [
                'commit' => config('sonar.commit'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'published_project' => fn () => $request->session()->get('published_project'),
            ],
        ];
    }

    /**
     * @return array{role: string, village_slug: string|null, uploads_used: int, upload_limit: int}|null
     */
    private function demoContext(Request $request): ?array
    {
        $villageId = $request->session()->get(SetCurrentVillage::DEMO_SESSION_KEY);
        if (! is_numeric($villageId)) {
            return null;
        }

        $village = Village::query()->select(['slug', 'demo_ai_uploads_count'])->find((int) $villageId);

        return [
            'role' => (string) $request->session()->get(SetCurrentVillage::DEMO_ROLE_SESSION_KEY, 'warga'),
            'village_slug' => $village?->slug,
            'uploads_used' => $village !== null ? $village->demo_ai_uploads_count : 0,
            'upload_limit' => (int) config('sonar.demo.upload_limit', 3),
        ];
    }
}
