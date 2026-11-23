<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware alias `role:<role1>,<role2>`.
 *
 * Restricts access to authenticated users having one of the specified roles.
 */
final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $allowed = array_filter(
            array_map(Role::tryFrom(...), $roles),
        );

        if (! in_array($user->role, $allowed, true)) {
            abort(403, 'Akses ditolak.');
        }

        return $next($request);
    }
}
