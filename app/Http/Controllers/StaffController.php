<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Rules\UniquePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manager logins, kept by the owner. Managers see sales, customers, stitching orders and tailors only.
 */
class StaffController extends Controller
{
    public function index(): View
    {
        $managerRole = $this->managerRole();

        return view('staff.index', [
            'managers' => $managerRole->users()->orderByDesc('is_active')->orderBy('name')->get(),
            'permissions' => $managerRole->permissions()->orderBy('group')->orderBy('id')->get()->groupBy('group'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, null);

        User::create($validated + [
            'role_id' => $this->managerRole()->id,
            'is_active' => true,
        ]);

        return back()->with('success', __('Manager :name added. They can log in now.', ['name' => $validated['name']]));
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->isManager(), 404);

        $validated = $this->validated($request, $staff);

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $staff->update($validated + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', __('Manager :name saved.', ['name' => $staff->name]));
    }

    private function managerRole(): Role
    {
        return Role::query()->where('slug', Role::MANAGER)->firstOrFail();
    }

    /**
     * @return array{name: string, username: string, phone: string|null, password?: string|null}
     */
    private function validated(Request $request, ?User $staff): array
    {
        $request->merge(['phone' => Customer::normalizePhone($request->input('phone'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($staff?->id)],
            'phone' => ['nullable', 'string', 'min:7', 'max:20', new UniquePhone('user', $staff?->id)],
            'password' => [$staff ? 'nullable' : 'required', 'string', 'min:6', 'max:100'],
        ]);
    }
}
