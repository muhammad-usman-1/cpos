<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Family members under one customer account (CUS04, MSR01).
 */
class CustomerProfileController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $this->validated($request, $customer);
        $profile = $customer->profiles()->create($validated);

        return redirect()->route('measurements.create', $profile)->with('success', __(':name added. Take the measurements now.', ['name' => $profile->name]));
    }

    public function update(Request $request, Customer $customer, CustomerProfile $profile): RedirectResponse
    {
        abort_unless($profile->customer_id === $customer->id, 404);

        $profile->update($this->validated($request, $customer, $profile));

        return back()->with('success', __('Profile saved.'));
    }

    public function destroy(Customer $customer, CustomerProfile $profile): RedirectResponse
    {
        abort_unless($profile->customer_id === $customer->id, 404);

        if ($customer->profiles()->count() === 1) {
            return back()->with('error', __('A customer needs at least one profile.'));
        }

        $profile->delete();

        return back()->with('success', __('Profile removed. Old orders keep their measurements.'));
    }

    /**
     * @return array{name: string, relation?: string|null, phone?: string|null, template: string, notes?: string|null}
     */
    private function validated(Request $request, Customer $customer, ?CustomerProfile $profile = null): array
    {
        if ($request->has('phone')) {
            $request->merge(['phone' => Customer::normalizePhone($request->input('phone'))]);
        }

        return $request->validate(CustomerProfile::detailRules($customer, $profile), CustomerProfile::detailMessages());
    }
}
