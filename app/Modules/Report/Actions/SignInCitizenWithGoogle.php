<?php

declare(strict_types=1);

namespace App\Modules\Report\Actions;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use Laravel\Socialite\Contracts\User as GoogleUser;

/**
 * Finds or creates the warga account for a Google sign-in (A4, PRD Fitur Inti 5.1 step 4, C3).
 *
 * Stores only what is needed to tell accounts apart (PRD Fitur Inti 8.3): the Google id and the email.
 * The Google display name is not kept; no NIK/KTP is ever asked.
 */
final class SignInCitizenWithGoogle
{
    /** Display name for every warga account; reporters are never shown by name (C11). */
    private const CITIZEN_NAME = 'Warga';

    /** Returns null when the Google account may not be used as a warga account. */
    public function handle(GoogleUser $googleUser): ?User
    {
        $googleId = (string) $googleUser->getId();
        $email = (string) $googleUser->getEmail();

        if ($googleId === '' || $email === '') {
            return null;
        }

        $existing = User::query()->where('google_id', $googleId)->first();

        if ($existing !== null) {
            return $existing->role === Role::Warga ? $existing : null;
        }

        // The email already belongs to another account (desa, BPD, or a warga with another Google id).
        // Never attach Google to it: that would let a Google login open a desa or BPD account.
        if (User::query()->where('email', $email)->exists()) {
            return null;
        }

        // createOrFirst: two callbacks racing for a new account end up with one row (unique google_id).
        return User::query()->createOrFirst(
            ['google_id' => $googleId],
            ['name' => self::CITIZEN_NAME, 'email' => $email, 'role' => Role::Warga, 'village_id' => null],
        );
    }
}
