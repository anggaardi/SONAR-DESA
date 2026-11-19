<?php

declare(strict_types=1);

namespace App\Modules\Core\Auth\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /login, POST /login, POST /logout (PRD Arsitektur 6.8, M0-07).
 */
final class LoginController
{
    public function create(): Response
    {
        return Inertia::render('core/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
