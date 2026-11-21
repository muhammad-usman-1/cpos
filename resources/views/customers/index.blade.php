<x-layouts.app :title="__('Customers')">
    <x-slot:actions>
        @can('customers.manage')
            <a href="{{ route('customers.create') }}" class="btn btn-primary">{{ __('Add customer') }}</a>
        @endcan
    </x-slot:actions>

    <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Phone or name') }}" class="input w-72" autofocus>
            <select name="tag" class="input w-36">
                <option value="">{{ __('All tags') }}</option>
                @foreach ($tags as $tag)
                    <option value="{{ $tag->value }}" @selected(request('tag') === $tag->value)>{{ $tag->label() }}</option>
                @endforeach
            </select>
            <select name="filter" class="input w-44">
                <option value="">{{ __('All customers') }}</option>
                <option value="balance" @selected(request('filter') === 'balance')>{{ __('With balance due') }}</option>
            </select>
            <button class="btn btn-secondary">{{ __('Search') }}</button>
        </form>
        <div class="text-sm text-slate-600">{{ __('Total receivable:') }} <span class="text-lg font-semibold text-slate-900">{{ number_format($totalReceivable, 2) }}</span></div>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Phone') }}</th><th>{{ __('City') }}</th><th>{{ __('Tag') }}</th><th class="text-end">{{ __('Open orders') }}</th><th class="text-end">{{ __('Balance due') }}</th></tr></thead>
            <tbody>
            @forelse ($customers as $customer)
                <tr data-href="{{ route('customers.show', $customer) }}">
                    <td>
                        <a href="{{ route('customers.show', $customer) }}" class="font-medium text-indigo-600 hover:underline">{{ $customer->name }}</a>
                        @unless ($customer->is_active)<span class="badge badge-gray">{{ __('Inactive') }}</span>@endunless
                    </td>
                    <td class="font-mono text-xs">{{ $customer->phone }}</td>
                    <td>{{ $customer->city }}</td>
                    <td>@if ($customer->tag)<span class="badge badge-gray">{{ $customer->tag->label() }}</span>@endif</td>
                    <td class="text-end">{{ $customer->open_orders_count ?: '' }}</td>
                    <td @class(['text-end font-semibold', 'text-rose-600' => (float) $customer->balance > 0, 'text-emerald-700' => (float) $customer->balance < 0])>{{ number_format($customer->balance, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-slate-500">{{ __('No customers found.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>
</x-layouts.app>
