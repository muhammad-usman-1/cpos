<x-layouts.app :title="__('Purchases')">
    <x-slot:actions>
        @can('purchases.manage')
            <a href="{{ route('purchases.create') }}" class="btn btn-primary">{{ __('New purchase') }}</a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="card card-body mb-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <x-field name="q" :label="__('Number or invoice')" :value="request('q')" />
        <x-select name="supplier_id" :label="__('Supplier')" :options="$suppliers->mapWithKeys(fn ($s) => [$s->id => $s->displayName()])" :selected="request('supplier_id')" :placeholder="__('All suppliers')" />
        <x-select name="status" :label="__('Status')" :options="['posted' => __('Posted'), 'cancelled' => __('Cancelled')]" :selected="request('status')" :placeholder="__('All')" />
        <x-field name="from" :label="__('From')" type="date" :value="request('from')" />
        <x-field name="to" :label="__('To')" type="date" :value="request('to')" />
        <div class="flex items-end"><button class="btn btn-secondary w-full">{{ __('Filter') }}</button></div>
    </form>

    <div class="mb-4 flex gap-6 text-sm text-slate-600">
        <span>{{ __('Total purchased:') }} <strong class="text-slate-900">{{ number_format($totals->total, 2) }}</strong></span>
        <span>{{ __('Paid:') }} <strong class="text-slate-900">{{ number_format($totals->paid, 2) }}</strong></span>
        <span>{{ __('Unpaid:') }} <strong class="text-slate-900">{{ number_format($totals->total - $totals->paid, 2) }}</strong></span>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Invoice') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Paid') }}</th><th class="text-end">{{ __('Balance') }}</th><th>{{ __('Status') }}</th><th>{{ __('By') }}</th></tr></thead>
            <tbody>
            @forelse ($purchases as $purchase)
                <tr>
                    <td><a href="{{ route('purchases.show', $purchase) }}" class="font-medium text-indigo-600 hover:underline">{{ $purchase->number }}</a></td>
                    <td class="whitespace-nowrap">{{ $purchase->purchase_date->translatedFormat('d M Y') }}</td>
                    <td>{{ $purchase->supplier->displayName() }}</td>
                    <td>{{ $purchase->invoice_no }}</td>
                    <td class="text-end">{{ number_format($purchase->total, 2) }}</td>
                    <td class="text-end">{{ number_format($purchase->paid_amount, 2) }}</td>
                    <td class="text-end">{{ number_format($purchase->balanceDue(), 2) }}</td>
                    <td><span @class(['badge', 'badge-green' => ! $purchase->isCancelled(), 'badge-red' => $purchase->isCancelled()])>{{ $purchase->status->label() }}</span></td>
                    <td class="text-xs">{{ $purchase->creator?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-6 text-center text-slate-500">{{ __('No purchases found.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $purchases->links() }}</div>
</x-layouts.app>
