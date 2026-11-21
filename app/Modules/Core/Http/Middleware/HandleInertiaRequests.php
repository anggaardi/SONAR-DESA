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
            'demo' => $request->session()->has(SetCurrentVillage::DEMO_SESSION_KEY) ? [
                'role' => (string) $request->session()->get(SetCurrentVillage::DEMO_ROLE_SESSION_KEY, 'warga'),
                'village_slug' => Village::whereKey((int) $request->session()->get(SetCurrentVillage::DEMO_SESSION_KEY))->value('slug'),
            ] : null,
            // F0-4: commit shown in the footer to confirm which build is live.
            'app' => [
                'commit' => config('sonar.commit'),
            ],
        ];
    }
}
