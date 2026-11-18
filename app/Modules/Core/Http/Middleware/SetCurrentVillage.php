<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Village\CurrentVillage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware alias `village`. Village comes from the logged-in desa/BPD user,
 * or from the demo session (module M6 writes `demo_village_id`).
 */
final class SetCurrentVillage
{
    public const DEMO_SESSION_KEY = 'demo_village_id';

    public function __construct(private readonly CurrentVillage $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $villageId = $request->user()?->village_id ?? $request->session()->get(self::DEMO_SESSION_KEY);

        if (is_numeric($villageId)) {
            $this->current->set((int) $villageId);
        }

        return $next($request);
    }
}
