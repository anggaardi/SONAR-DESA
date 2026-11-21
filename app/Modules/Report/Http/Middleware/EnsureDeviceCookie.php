<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives the browser a random device token for "1 perangkat 1 pelapor" (D-010). The cookie is encrypted by the
 * web middleware and HttpOnly. Only an HMAC of the token is stored (SubmitReportRequest::deviceHash); the token
 * itself and the IP address never reach the database.
 */
final class EnsureDeviceCookie
{
    public const COOKIE = 'sonar_device';

    private const TOKEN_LENGTH = 40;

    /** Two years: long enough to outlive a review cycle, cleared by the visitor whenever they like. */
    private const LIFETIME_MINUTES = 60 * 24 * 365 * 2;

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || ! preg_match('/^[A-Za-z0-9]{'.self::TOKEN_LENGTH.'}$/', $token)) {
            $token = Str::random(self::TOKEN_LENGTH);
            // Visible to this request too, so a first send without the cookie still has a device.
            $request->cookies->set(self::COOKIE, $token);
            Cookie::queue(Cookie::make(self::COOKIE, $token, self::LIFETIME_MINUTES, httpOnly: true, sameSite: 'lax'));
        }

        return $next($request);
    }
}
