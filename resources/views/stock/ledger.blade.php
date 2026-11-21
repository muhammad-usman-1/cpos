<x-layouts.app :title="'Stock ledger · '.$variant->displayName()">
    <x-slot:actions>
        <a href="{{ route('products.show', $variant->product) }}" class="btn btn-secondary">{{ __('Back to product') }}</a>
        @can('products.manage')
            <a href="{{ route('products.edit', $variant->product) }}" class="btn btn-primary">{{ __('Change quantity') }}</a>
        @endcan
    </x-slot:actions>

    <div class="mb-4 flex flex-wrap items-end gap-6">
        <div class="card card-body">
            <div class="text-sm text-slate-500">{{ __('Current stock') }}</div>
            <div class="text-2xl font-semibold">{{ (float) $variant->stock_qty }} <span class="text-sm font-normal text-slate-500">{{ $variant->product->unit->label() }}</span></div>
            <div class="font-mono text-xs text-slate-500">{{ $variant->sku }}</div>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <x-field name="from" :label="__('From')" type="date" :value="request('from')" />
            <x-field name="to" :label="__('To')" type="date" :value="request('to')" />
            <x-select name="type" :label="__('Type')" :options="collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])" :selected="request('type')" :placeholder="__('All')" />
            <button class="btn btn-secondary">{{ __('Filter') }}</button>
        </form>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
            <tr>
                <th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Notes') }}</th>
                <th class="text-end">{{ __('In') }}</th><th class="text-end">{{ __('Out') }}</th><th class="text-end">{{ __('Balance') }}</th>
                @can('cost.view')<th class="text-end">{{ __('Unit cost') }}</th>@endcan
                <th>{{ __('By') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($movements as $movement)
                <tr @class(['text-slate-400' => $movement->reversed_at])>
                    <td class="whitespace-nowrap">{{ $movement->moved_at->translatedFormat('d M Y H:i') }}</td>
                    <td>
                        {{ $movement->type->label() }}
                        @if ($movement->reason)<span class="badge badge-gray">{{ $movement->reason->label() }}</span>@endif
                        @if ($movement->reversed_at)<span class="badge badge-gray">{{ __('Reversed') }}</span>@endif
                    </td>
                    <td class="whitespace-nowrap">
                        @php($reference = $movement->reference)
                        @if ($reference instanceof \App\Models\Purchase)
                            @can('purchases.view')<a href="{{ route('purchases.show', $reference) }}" class="text-indigo-600 hover:underline">{{ $reference->number }}</a>@else{{ $reference->number }}@endcan
                        @elseif ($reference instanceof \App\Models\PurchaseReturn)
                            @can('purchases.view')<a href="{{ route('purchase-returns.show', $reference) }}" class="text-indigo-600 hover:underline">{{ $reference->number }}</a>@else{{ $reference->number }}@endcan
                        @elseif ($reference instanceof \App\Models\StockTake)
                            {{ $reference->number }}
                        @endif
                    </td>
                    <td class="text-xs">{{ $movement->notes }}</td>
                    <td class="text-end text-emerald-700">{{ (float) $movement->qty_in ?: '' }}</td>
                    <td class="text-end text-rose-700">{{ (float) $movement->qty_out ?: '' }}</td>
                    <td class="text-end font-medium">{{ (float) $movement->balance_after }}</td>
                    @can('cost.view')<td class="text-end">{{ $movement->unit_cost !== null ? number_format($movement->unit_cost, 2) : '' }}</td>@endcan
                    <td class="text-xs">{{ $movement->user?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-6 text-center text-slate-500">{{ __('No stock movements yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $movements->links() }}</div>
</x-layouts.app>
