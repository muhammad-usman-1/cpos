<x-layouts.app :title="$supplier->exists ? __('Edit supplier: ').$supplier->name : __('Add supplier')">
    <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="card card-body space-y-4">
        @csrf
        @if ($supplier->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" :label="__('Supplier name')" :value="$supplier->name" required />
            <x-field name="shop_name" :label="__('Shop / company name')" :value="$supplier->shop_name" />
            <x-field name="phone" :label="__('Phone')" :value="$supplier->phone" inputmode="tel" autocomplete="off" data-phone-check data-ignore-type="supplier" data-ignore-id="{{ $supplier->id }}" />
            <x-field name="city" :label="__('City')" :value="$supplier->city" />
            <x-field name="address" :label="__('Address')" :value="$supplier->address" class="sm:col-span-2" />
            @unless ($supplier->exists)
                <x-field name="opening_balance" :label="__('Opening balance payable')" type="number" step="0.01" min="0" value="0" :hint="__('Amount you already owe this supplier before using the system.')" />
            @endunless
        </div>
        <x-field name="notes" :label="__('Notes')" type="textarea" :value="$supplier->notes" />
        <x-checkbox name="is_active" :label="__('Active')" :checked="$supplier->is_active ?? true" />

        <div class="flex gap-2">
            <button class="btn btn-primary">{{ __('Save supplier') }}</button>
            <a href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
