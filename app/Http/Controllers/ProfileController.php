<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Rules\UniquePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The owner edits their own login details: name, username, phone, email and password.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['phone' => Customer::normalizePhone($request->input('phone'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'min:7', 'max:20', new UniquePhone('user', $user->id)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users')->ignore($user)],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => __('Your current password is not correct.'),
            'current_password.required_with' => __('Enter your current password to set a new one.'),
        ]);

        unset($validated['current_password']);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return back()->with('success', __('Profile saved.'));
    }
}
