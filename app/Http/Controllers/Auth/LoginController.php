<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceSessionRules;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login with username or phone number and password (AUTH01).
 */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($credentials['login']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'login' => __('Too many login attempts. Try again in :value seconds.', ['value' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        $login = trim($credentials['login']);
        $user = User::query()
            ->where('username', $login)
            ->orWhere('phone', Customer::normalizePhone($login) ?? $login)
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey);
            AuditLog::record('login_failed', $user, null, null, 'Login attempt for "'.Str::limit($login, 60).'"');

            throw ValidationException::withMessages(['login' => __('These credentials do not match our records.')]);
        }

        if (! $user->canLogIn()) {
            throw ValidationException::withMessages(['login' => __('Only the shop owner and managers can log in.')]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => __('This account has been deactivated. Contact the shop owner.')]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->put(EnforceSessionRules::LAST_ACTIVITY_KEY, time());

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        AuditLog::record('login', $user);

        return redirect()->to($this->homeAfterLogin($request));
    }

    /**
     * The counter screen opens after login; a deeper page the user was sent away from is kept.
     */
    private function homeAfterLogin(Request $request): string
    {
        $counter = $request->user()->can('sales.create') ? route('sales.create') : route('dashboard');
        $intended = $request->session()->pull('url.intended');

        if (! $intended || rtrim($intended, '/') === rtrim(route('dashboard'), '/')) {
            return $counter;
        }

        return $intended;
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditLog::record('logout', $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', $request->input('reason') === 'idle'
            ? __('You were logged out because of inactivity.')
            : __('You have been logged out.'));
    }
}
