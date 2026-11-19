<?php

declare(strict_types=1);

namespace App\Modules\Core\Auth\Http;

use App\Modules\Core\Auth\Role;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Isi email akun desa atau BPD.',
            'email.email' => 'Format email belum benar, contoh: nama@desa.id.',
            'password.required' => 'Isi kata sandi.',
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => $this->string('email')->lower()->toString(),
            'password' => $this->string('password')->toString(),
            'role' => [Role::Desa->value, Role::Bpd->value],
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak cocok. Periksa lagi, atau hubungi tim Sonar Desa bila lupa.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate($this->string('email')->lower().'|'.$this->ip());
    }
}
