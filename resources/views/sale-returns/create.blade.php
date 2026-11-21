<x-layouts.app :title="'Return / exchange · '.$sale->number">
    <form method="POST" action="{{ route('sale-returns.store', $sale) }}" class="space-y-6" data-confirm="{{ __('Save this return? Stock comes back and the refund is recorded.') }}">
        @csrf
        <div class="card card-body grid gap-4 sm:grid-cols-4">
            <div>
                <span class="label">{{ __('Customer') }}</span>
                <div class="py-2 text-sm">{{ $sale->customer?->name ?? __('Walk-in') }}</div>
            </div>
            <x-field name="return_date" :label="__('Return date')" type="date" :value="today()->toDateString()" required />
            <x-select name="refund_method" :label="__('Refund')" :options="$methods->mapWithKeys(fn ($m) => [$m->value => $m->label()])" :selected="$sale->customer_id ? 'balance' : 'cash'" required />
            <x-field name="notes" :label="__('Reason')" />
        </div>

        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Sold') }}</th><th class="text-end">{{ __('Already returned') }}</th><th class="text-end">{{ __('Refund price each') }}</th><th class="w-40 text-end">{{ __('Return qty') }}</th></tr></thead>
                <tbody>
                @foreach ($sale->items as $index => $item)
                    <tr>
                        <td>{{ $item->variant->displayName() }} <span class="font-mono text-xs text-slate-500">{{ $item->variant->sku }}</span></td>
                        <td class="text-end">{{ (float) $item->quantity }}</td>
                        <td class="text-end">{{ (float) $item->returned_qty ?: '' }}</td>
                        <td class="text-end">{{ number_format((float) $item->amount / (float) $item->quantity * $factor, 2) }}</td>
                        <td class="text-end">
                            <input type="hidden" name="items[{{ $index }}][sale_item_id]" value="{{ $item->id }}">
                            <input type="number" min="0" max="{{ $item->returnableQty() }}" step="{{ $item->variant->product->allowsFractionalQuantity() ? '0.001' : '1' }}"
                                   name="items[{{ $index }}][quantity]" value="{{ old('items.'.$index.'.quantity', 0) }}" class="input text-end" @disabled($item->returnableQty() <= 0)>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-sm text-slate-600">{{ __('The refund price includes this item\'s share of any bill discount. For an exchange, save the return and the new sale opens straight away.') }}</p>
        <div class="flex flex-wrap gap-2">
            <button class="btn btn-primary">{{ __('Save return') }}</button>
            <button name="exchange" value="1" class="btn btn-secondary">{{ __('Save return and start exchange sale') }}</button>
            <a href="{{ route('sales.show', $sale) }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
