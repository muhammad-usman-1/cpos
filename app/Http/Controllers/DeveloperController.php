<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureDeveloperSession;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Developer login: when the owner forgets their password, the developer sets a new one here.
 * The developer account is kept in .env (php artisan developer:password), not in the users table.
 */
class DeveloperController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function create(): View
    {
        return view('developer.login', ['isConfigured' => $this->isConfigured()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'developer|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'username' => 'Too many attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        $isValid = $this->isConfigured()
            && hash_equals((string) config('developer.username'), $credentials['username'])
            && Hash::check($credentials['password'], (string) config('developer.password_hash'));

        if (! $isValid) {
            RateLimiter::hit($throttleKey, 300);
            AuditLog::record('developer_login_failed', null, null, null, 'Developer login attempt for "'.Str::limit($credentials['username'], 60).'"');

            throw ValidationException::withMessages(['username' => 'These developer credentials are not correct.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->session()->put(EnsureDeveloperSession::SESSION_KEY, now()->addMinutes(config('developer.session_minutes'))->timestamp);
        AuditLog::record('developer_login', null);

        return redirect()->route('developer.panel');
    }

    public function panel(): View
    {
        return view('developer.panel', ['owner' => $this->owner()]);
    }

    /**
     * Set a new password for the owner, and optionally a new username; the account is switched back on.
     */
    public function resetOwner(Request $request): RedirectResponse
    {
        $owner = $this->owner();

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($owner->id)],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $owner->forceFill([
            'username' => $validated['username'],
            'password' => $validated['password'],
            'is_active' => true,
            'remember_token' => Str::random(60),
        ])->save();

        AuditLog::record('owner_password_reset', $owner, null, null, 'Owner password reset from the developer login');

        return redirect()->route('developer.panel')->with('success', "New password saved. The owner can now log in as \"{$owner->username}\".");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(EnsureDeveloperSession::SESSION_KEY);
        $request->session()->regenerate();

        return redirect()->route('developer.login')->with('status', 'Developer session closed.');
    }

    private function isConfigured(): bool
    {
        return filled(config('developer.username')) && filled(config('developer.password_hash'));
    }

    private function owner(): User
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('slug', Role::OWNER))
            ->oldest('id')
            ->firstOrFail();
    }
}
