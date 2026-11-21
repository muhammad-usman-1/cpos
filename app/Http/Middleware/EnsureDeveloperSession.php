<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only while the developer has logged in recently (see DeveloperController).
 */
class EnsureDeveloperSession
{
    public const SESSION_KEY = 'developer_until';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ((int) $request->session()->get(self::SESSION_KEY, 0) < time()) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('developer.login')->with('status', 'Please log in as the developer.');
        }

        return $next($request);
    }
}
