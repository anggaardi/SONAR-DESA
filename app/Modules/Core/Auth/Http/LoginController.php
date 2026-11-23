<?php

declare(strict_types=1);

namespace App\Modules\Core\Auth\Http;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Http\Middleware\SetCurrentVillage;
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
    /**
     * Demo accounts may still open the form, so a judge playing desa/BPD can switch to a real account.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if ($this->hasRealAccount($request)) {
            return $this->redirectUser($request->user());
        }

        return Inertia::render('core/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if ($this->hasRealAccount($request)) {
            return $this->redirectUser($request->user());
        }

        $request->authenticate();
        $request->session()->regenerate();

        // A real account never keeps the demo village of an earlier demo visit.
        $request->session()->forget([SetCurrentVillage::DEMO_SESSION_KEY, SetCurrentVillage::DEMO_ROLE_SESSION_KEY]);

        return redirect()->intended($this->defaultLanding($request->user()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function hasRealAccount(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $user->village?->is_demo !== true;
    }

    private function redirectUser(?User $user): RedirectResponse
    {
        return redirect()->to($this->defaultLanding($user));
    }

    private function defaultLanding(?User $user): string
    {
        return route($user?->hasRole(Role::Desa) ? 'desa.dashboard' : 'home');
    }
}
