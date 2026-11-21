<x-layouts.app :title="'Return items from '.$purchase->number">
    <form method="POST" action="{{ route('purchase-returns.store', $purchase) }}" class="space-y-6" data-confirm="{{ __('Save this return? Stock will be reduced and the supplier balance adjusted.') }}">
        @csrf
        <div class="card card-body grid gap-4 sm:grid-cols-3">
            <div>
                <span class="label">{{ __('Supplier') }}</span>
                <div class="py-2 text-sm">{{ $purchase->supplier->displayName() }}</div>
            </div>
            <x-field name="return_date" :label="__('Return date')" type="date" :value="today()->toDateString()" required />
            <x-field name="notes" :label="__('Reason / notes')" />
        </div>

        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>{{ __('Product') }}</th><th class="text-end">{{ __('Purchased') }}</th><th class="text-end">{{ __('Already returned') }}</th><th class="text-end">{{ __('Rate') }}</th><th class="w-40 text-end">{{ __('Return qty') }}</th></tr></thead>
                <tbody>
                @foreach ($purchase->items as $index => $item)
                    <tr>
                        <td>{{ $item->variant->displayName() }} <span class="font-mono text-xs text-slate-500">{{ $item->variant->sku }}</span></td>
                        <td class="text-end">{{ (float) $item->quantity }}</td>
                        <td class="text-end">{{ (float) $item->returned_qty }}</td>
                        <td class="text-end">{{ number_format($item->rate, 2) }}</td>
                        <td class="text-end">
                            <input type="hidden" name="items[{{ $index }}][purchase_item_id]" value="{{ $item->id }}">
                            <input type="number" min="0" max="{{ $item->returnableQty() }}" step="{{ $item->variant->product->allowsFractionalQuantity() ? '0.001' : '1' }}"
                                   name="items[{{ $index }}][quantity]" value="{{ old('items.'.$index.'.quantity', 0) }}" class="input text-end" @disabled($item->returnableQty() <= 0)>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-sm text-slate-600">{{ __('Items are returned at their purchase rate. After a return, this purchase can no longer be edited or cancelled.') }}</p>
        <div class="flex gap-2">
            <button class="btn btn-primary">{{ __('Save return') }}</button>
            <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
