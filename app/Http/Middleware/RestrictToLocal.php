<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictToLocal
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_IPS = [
        '127.0.0.1',
        '::1',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->ip(), self::ALLOWED_IPS, true) && ! app()->environment('local', 'testing')) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
