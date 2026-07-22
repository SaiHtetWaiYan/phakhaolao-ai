<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifies a mobile client by a device token it generates once and stores
 * locally, sent as "X-Device-Token" (or a bearer token).
 *
 * This is the header-based equivalent of the web guest cookie: there is no
 * login, so the token is the only identity. It is written to the request so
 * controllers can own conversations by it.
 */
class ResolveDeviceToken
{
    public const ATTRIBUTE = 'device_token';

    public function handle(Request $request, Closure $next): Response
    {
        $token = trim((string) ($request->header('X-Device-Token') ?: $request->bearerToken()));

        if ($token === '' || ! $this->isValid($token)) {
            return response()->json([
                'message' => 'A valid X-Device-Token header is required. Generate a UUID once per install and reuse it.',
            ], 401);
        }

        $request->attributes->set(self::ATTRIBUTE, $token);

        return $next($request);
    }

    /**
     * Accepts a UUID, or any opaque token of a sane length, so clients are not
     * forced into one format while still rejecting junk.
     */
    private function isValid(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $token);
    }
}
