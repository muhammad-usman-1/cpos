<x-layouts.app :title="__('Suppliers')">
    <x-slot:actions>
        @can('suppliers.manage')
            <a href="{{ route('suppliers.create') }}" class="btn btn-primary">{{ __('Add supplier') }}</a>
        @endcan
    </x-slot:actions>

    <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Name, shop, phone or city') }}" class="input w-72">
            <select name="status" class="input w-44">
                <option value="">{{ __('All suppliers') }}</option>
                <option value="payable" @selected(request('status') === 'payable')>{{ __('With balance payable') }}</option>
                <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
            </select>
            <button class="btn btn-secondary">{{ __('Search') }}</button>
        </form>
        <div class="text-sm text-slate-600">{{ __('Total payable:') }} <span class="text-lg font-semibold text-slate-900">{{ number_format($totalPayable, 2) }}</span></div>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Shop') }}</th><th>{{ __('Phone') }}</th><th>{{ __('City') }}</th><th class="text-end">{{ __('Balance payable') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($suppliers as $supplier)
                <tr>
                    <td>
                        <a href="{{ route('suppliers.show', $supplier) }}" class="font-medium text-indigo-600 hover:underline">{{ $supplier->name }}</a>
                        @unless ($supplier->is_active)<span class="badge badge-gray">{{ __('Inactive') }}</span>@endunless
                    </td>
                    <td>{{ $supplier->shop_name }}</td>
                    <td>{{ $supplier->phone }}</td>
                    <td>{{ $supplier->city }}</td>
                    <td @class(['text-end font-semibold', 'text-rose-600' => (float) $supplier->balance > 0, 'text-emerald-700' => (float) $supplier->balance < 0])>{{ number_format($supplier->balance, 2) }}</td>
                    <td class="whitespace-nowrap text-end">
                        @can('suppliers.pay')
                            <a href="{{ route('suppliers.payments.create', $supplier) }}" class="btn btn-secondary btn-sm">{{ __('Pay') }}</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-slate-500">{{ __('No suppliers found.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $suppliers->links() }}</div>
</x-layouts.app>
