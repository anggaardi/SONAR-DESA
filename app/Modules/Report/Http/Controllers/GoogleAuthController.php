<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Controllers;

use App\Modules\Report\Actions\SignInCitizenWithGoogle;
use App\Modules\Report\Enums\GoogleSignInFailure;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Google sign-in for warga (PRD Fitur Inti 5.1 step 4, F11, C3; PRD Arsitektur 6.8, M4-01).
 * The auth middleware stores the checklist URL as url.intended, so a successful sign-in returns there.
 */
final class GoogleAuthController
{
    public function redirect(): RedirectResponse|InertiaResponse
    {
        // Socialite only checks that the config keys exist, so empty .env values would send the visitor to a
        // Google error page. Show our own retry page instead.
        if (blank(config('services.google.client_id')) || blank(config('services.google.client_secret'))) {
            Log::error('auth.google.not_configured');

            return $this->failed(GoogleSignInFailure::Failed);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, SignInCitizenWithGoogle $signIn): RedirectResponse|InertiaResponse
    {
        // Google sends ?error=access_denied when the visitor closes the consent screen.
        if ($request->filled('error')) {
            return $this->failed(GoogleSignInFailure::Cancelled);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException|GuzzleException $exception) {
            Log::warning('auth.google.failed', ['exception' => $exception::class]);

            return $this->failed(GoogleSignInFailure::Failed);
        }

        $user = $signIn->handle($googleUser);

        if ($user === null) {
            Log::notice('auth.google.refused');

            return $this->failed(GoogleSignInFailure::NotAllowed);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /** url.intended stays in the session, so "Coba lagi" still returns to the same checklist (F11). */
    private function failed(GoogleSignInFailure $reason): InertiaResponse
    {
        return Inertia::render('report/GoogleSignInFailed', [
            'reason' => $reason->value,
            'retryUrl' => route('auth.google'),
        ]);
    }
}
