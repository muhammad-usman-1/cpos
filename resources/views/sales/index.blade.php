<x-layouts.app :title="__('Sales')">
    <x-slot:actions>
        @can('sales.create')
            <a href="{{ route('sales.create') }}" class="btn btn-primary">{{ __('New sale') }}</a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="card card-body mb-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <x-field name="q" :label="__('Invoice, phone or name')" :value="request('q')" />
        <x-field name="from" :label="__('From')" type="date" :value="request('from')" />
        <x-field name="to" :label="__('To')" type="date" :value="request('to')" />
        <x-select name="user_id" :label="__('Salesman')" :options="$users->pluck('name', 'id')" :selected="request('user_id')" :placeholder="__('All')" />
        <div class="flex items-end"><x-checkbox name="credit" :label="__('Credit sales only')" :checked="request()->boolean('credit')" /></div>
        <div class="flex items-end"><button class="btn btn-secondary w-full">{{ __('Filter') }}</button></div>
    </form>

    <div class="mb-4 flex flex-wrap gap-6 text-sm text-slate-600">
        <span>{{ __('Bills:') }} <strong class="text-slate-900">{{ $totals->bills }}</strong></span>
        <span>{{ __('Sales:') }} <strong class="text-slate-900">{{ number_format($totals->total, 2) }}</strong></span>
        <span>{{ __('Received:') }} <strong class="text-slate-900">{{ number_format($totals->paid, 2) }}</strong></span>
        <span>{{ __('On credit:') }} <strong class="text-slate-900">{{ number_format($totals->credit, 2) }}</strong></span>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Paid') }}</th><th class="text-end">{{ __('Credit') }}</th><th>{{ __('By') }}</th></tr></thead>
            <tbody>
            @forelse ($sales as $sale)
                <tr data-href="{{ route('sales.show', $sale) }}">
                    <td><a href="{{ route('sales.show', $sale) }}" class="font-medium text-indigo-600 hover:underline">{{ $sale->number }}</a></td>
                    <td class="whitespace-nowrap">{{ $sale->completed_at?->translatedFormat('d M Y H:i') ?? $sale->sale_date->translatedFormat('d M Y') }}</td>
                    <td>{{ $sale->customer?->name ?? __('Walk-in') }}</td>
                    <td class="text-end">{{ number_format($sale->total, 2) }}</td>
                    <td class="text-end">{{ number_format($sale->paid_amount, 2) }}</td>
                    <td class="text-end">{{ (float) $sale->credit_amount ? number_format($sale->credit_amount, 2) : '' }}</td>
                    <td class="text-xs">{{ $sale->creator?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">{{ __('No sales found.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $sales->links() }}</div>
</x-layouts.app>
