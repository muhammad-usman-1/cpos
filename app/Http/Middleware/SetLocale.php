<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the language the user picked (English or Urdu). Guests, such as on the login page, use the session choice.
 */
class SetLocale
{
    public const SUPPORTED = ['en', 'ur'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale', config('app.locale'));

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
            Carbon::setLocale($locale);
        }

        return $next($request);
    }
}
