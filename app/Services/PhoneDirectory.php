<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerProfile;
use App\Models\Supplier;
use App\Models\User;

/**
 * One phone number belongs to one person across the whole system: customers (main and alternate phone),
 * family members, suppliers and staff. Used for the live warning under phone fields and for validation.
 */
class PhoneDirectory
{
    /**
     * Who already uses this number, or null when it is free.
     *
     * @param  string|null  $ignoreType  customer, profile, supplier or user: the record being edited
     * @return array{owner: string, url: string|null}|null
     */
    public function find(?string $phone, ?string $ignoreType = null, ?int $ignoreId = null): ?array
    {
        $phone = Customer::normalizePhone($phone);

        if ($phone === null || strlen($phone) < 7) {
            return null;
        }

        $customer = Customer::query()
            ->where(fn ($q) => $q->where('phone', $phone)->orWhere('alt_phone', $phone))
            ->when($ignoreType === 'customer', fn ($q) => $q->whereKeyNot($ignoreId))
            ->first(['id', 'name', 'phone']);

        if ($customer) {
            return [
                'owner' => $customer->phone === $phone
                    ? __(':name (customer)', ['name' => $customer->name])
                    : __(':name (customer, alternate phone)', ['name' => $customer->name]),
                'url' => route('customers.show', $customer),
            ];
        }

        $profile = CustomerProfile::query()
            ->with('customer:id,name')
            ->where('phone', $phone)
            ->whereHas('customer')
            ->when($ignoreType === 'profile', fn ($q) => $q->whereKeyNot($ignoreId))
            ->first();

        if ($profile) {
            return [
                'owner' => __(':name (:relation of :customer)', [
                    'name' => $profile->name,
                    'relation' => $profile->relation?->label() ?? __('Family member'),
                    'customer' => $profile->customer->name,
                ]),
                'url' => route('customers.show', $profile->customer_id),
            ];
        }

        $supplier = Supplier::query()
            ->where('phone', $phone)
            ->when($ignoreType === 'supplier', fn ($q) => $q->whereKeyNot($ignoreId))
            ->first(['id', 'name']);

        if ($supplier) {
            return ['owner' => __(':name (supplier)', ['name' => $supplier->name]), 'url' => null];
        }

        $user = User::query()
            ->where('phone', $phone)
            ->when($ignoreType === 'user', fn ($q) => $q->whereKeyNot($ignoreId))
            ->first(['id', 'name']);

        if ($user) {
            return ['owner' => __(':name (staff)', ['name' => $user->name]), 'url' => null];
        }

        return null;
    }
}
