<?php

declare(strict_types=1);

namespace App\Modules\Desa\Http\Middleware;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware alias `desa`. Allows only authenticated users with Role::Desa.
 * BPD and warga are redirected; unauthenticated visitors go to the login page.
 */
final class RequireDesaRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! $user->hasRole(Role::Desa)) {
            abort(403, 'Halaman ini hanya untuk Pemerintah Desa.');
        }

        return $next($request);
    }
}
