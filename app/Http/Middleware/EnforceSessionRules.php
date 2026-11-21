<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out deactivated users immediately and enforces the configurable inactivity timeout (AUTH03, AUTH06).
 */
class EnforceSessionRules
{
    public const LAST_ACTIVITY_KEY = 'last_activity_at';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->canLogIn()) {
            return $this->logout($request, __('Only the shop owner and managers can log in.'), 'logout_not_owner');
        }

        if (! $user->is_active) {
            return $this->logout($request, __('Your account has been deactivated.'), 'logout_deactivated');
        }

        $timeoutMinutes = Setting::idleTimeoutMinutes();
        $lastActivity = (int) $request->session()->get(self::LAST_ACTIVITY_KEY, time());

        if ($timeoutMinutes > 0 && time() - $lastActivity > $timeoutMinutes * 60) {
            return $this->logout($request, __('You were logged out after :minutes minutes of inactivity.', ['minutes' => $timeoutMinutes]), 'logout_idle');
        }

        $request->session()->put(self::LAST_ACTIVITY_KEY, time());

        return $next($request);
    }

    private function logout(Request $request, string $message, string $event): Response
    {
        AuditLog::record($event, $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()->route('login')->with('status', $message);
    }
}
