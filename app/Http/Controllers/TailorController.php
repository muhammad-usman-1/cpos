<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Rules\UniquePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Tailors and workshops that stitching orders are given to. Only the owner logs in, so tailors are just
 * names and phone numbers; "active" means they still take work.
 */
class TailorController extends Controller
{
    public function index(): View
    {
        return view('tailors.index', [
            'tailors' => User::query()->whereHas('role', fn ($q) => $q->where('slug', 'tailor'))->withCount('tailorAssignments')->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        User::create($validated + [
            'username' => 'tailor-'.Str::lower(Str::random(8)),
            'password' => Str::random(40),
            'role_id' => Role::query()->where('slug', 'tailor')->value('id'),
            'is_active' => true,
        ]);

        return back()->with('success', __('Tailor :name added.', ['name' => $validated['name']]));
    }

    public function update(Request $request, User $tailor): RedirectResponse
    {
        abort_unless($tailor->role?->slug === 'tailor', 404);

        $tailor->update($this->validated($request, $tailor) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', __('Tailor saved.'));
    }

    /**
     * @return array{name: string, phone: string|null}
     */
    private function validated(Request $request, ?User $tailor = null): array
    {
        $request->merge(['phone' => Customer::normalizePhone($request->input('phone'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'min:7', 'max:20', new UniquePhone('user', $tailor?->id)],
        ]);
    }
}
