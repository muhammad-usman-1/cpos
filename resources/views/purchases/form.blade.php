@php
    $initialItems = old('items') ? collect(old('items'))->map(fn ($item) => [
        'product_variant_id' => $item['product_variant_id'] ?? null,
        'label' => $item['label'] ?? 'Product #'.($item['product_variant_id'] ?? ''),
        'sku' => $item['sku'] ?? '',
        'fractional' => true,
        'quantity' => $item['quantity'] ?? 1,
        'rate' => $item['rate'] ?? 0,
    ])->values()->all() : $items;
@endphp

<x-layouts.app :title="$purchase->exists ? __('Edit purchase ').$purchase->number : __('New purchase')">
    @if ($purchase->exists)
        <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ __('Saving reverses this purchase\'s stock, cost and supplier ledger entries and posts them again with the new values. Both sets of entries stay in the history.') }}
        </div>
    @endif

    <form method="POST" action="{{ $purchase->exists ? route('purchases.update', $purchase) : route('purchases.store') }}" enctype="multipart/form-data"
          class="space-y-6" data-purchase-form data-items="{{ json_encode($initialItems) }}">
        @csrf
        @if ($purchase->exists) @method('PUT') @endif

        <div class="card card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-select name="supplier_id" :label="__('Supplier')" :options="$suppliers->mapWithKeys(fn ($s) => [$s->id => $s->displayName().' · balance '.number_format($s->balance, 0)])" :selected="$purchase->supplier_id" :placeholder="__('Choose supplier')" required class="lg:col-span-2" />
            <x-field name="purchase_date" :label="__('Date')" type="date" :value="$purchase->purchase_date?->toDateString()" required />
            <x-field name="invoice_no" :label="__('Supplier invoice no.')" :value="$purchase->invoice_no" />
        </div>

        <div class="card">
            <div class="border-b border-slate-200 px-5 py-3">
                <div class="relative">
                    <label class="label" for="product-search">{{ __('Add items') }}</label>
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute start-4 top-1/2 h-6 w-6 -translate-y-1/2 text-slate-400" />
                        <input id="product-search" type="search" class="input h-14 rounded-lg ps-13 text-lg" placeholder="{{ __('Scan barcode or type name / code…') }}" data-product-search data-url="{{ route('products.lookup') }}" autocomplete="off" autofocus>
                    </div>
                    <div data-search-results class="absolute z-10 mt-1 hidden max-h-96 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"></div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('A scanned barcode is added at once. When you type a name, tick the colours to add.') }}</p>
                </div>
            </div>
            <table class="table">
                <thead><tr><th>{{ __('Product') }}</th><th class="w-36 text-end">{{ __('Quantity') }}</th><th class="w-40 text-end">{{ __('Rate') }}</th><th class="w-36 text-end">{{ __('Amount') }}</th><th class="w-10"></th></tr></thead>
                <tbody data-rows="items"></tbody>
                <tbody>
                    <tr data-empty-row><td colspan="5" class="py-6 text-center text-slate-500">{{ __('No items yet. Search a product above.') }}</td></tr>
                </tbody>
            </table>
            <template data-row-template="items">
                <tr data-row>
                    <td>
                        <input type="hidden" name="items[__INDEX__][product_variant_id]" data-field="product_variant_id">
                        <input type="hidden" name="items[__INDEX__][label]" data-field="label">
                        <input type="hidden" name="items[__INDEX__][sku]" data-field="sku">
                        <div class="font-medium" data-label-text></div>
                    </td>
                    <td><input type="number" name="items[__INDEX__][quantity]" min="1" step="1" required class="input text-end" data-field="quantity"></td>
                    <td><input type="number" name="items[__INDEX__][rate]" min="0" step="0.01" required class="input text-end" data-field="rate"></td>
                    <td class="text-end font-medium" data-amount>0.00</td>
                    <td><button type="button" class="text-rose-600 hover:text-rose-800" data-remove-row title="{{ __('Remove') }}">✕</button></td>
                </tr>
            </template>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="card card-body space-y-4 lg:col-span-2">
                <x-field name="notes" :label="__('Notes')" type="textarea" :value="$purchase->notes" />
                <div>
                    <label class="label" for="attachment">{{ __('Supplier invoice photo or scan') }}</label>
                    <input id="attachment" type="file" name="attachment" accept="image/*,application/pdf" class="block text-sm">
                    @if ($purchase->attachmentUrl())
                        <p class="mt-1 text-xs"><a href="{{ $purchase->attachmentUrl() }}" target="_blank" class="text-indigo-600 hover:underline">{{ __('Current attachment') }}</a> {{ __('· uploading a new file replaces it.') }}</p>
                    @endif
                    @error('attachment')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="card card-body space-y-3">
                <div class="flex justify-between text-sm"><span>{{ __('Subtotal') }}</span><span class="font-medium" data-subtotal>0.00</span></div>
                <div class="flex items-center justify-between gap-3 text-sm">
                    <label for="discount">{{ __('Discount') }}</label>
                    <input id="discount" type="number" step="0.01" min="0" name="discount" value="{{ old('discount', (float) $purchase->discount) }}" class="input w-36 text-end">
                </div>
                <div class="flex items-center justify-between gap-3 text-sm">
                    <label for="other_charges">{{ __('Other charges (freight etc.)') }}</label>
                    <input id="other_charges" type="number" step="0.01" min="0" name="other_charges" value="{{ old('other_charges', (float) $purchase->other_charges) }}" class="input w-36 text-end">
                </div>
                <div class="flex justify-between border-t border-slate-200 pt-3 text-base font-semibold"><span>{{ __('Total') }}</span><span data-total>0.00</span></div>
                <div class="flex items-center justify-between gap-3 text-sm">
                    <label for="paid_amount">{{ __('Paid now') }}</label>
                    <input id="paid_amount" type="number" step="0.01" min="0" name="paid_amount" value="{{ old('paid_amount', (float) $purchase->paid_amount) }}" class="input w-36 text-end">
                </div>
                <x-select name="payment_method" :label="__('Payment method')" :options="collect($methods)->mapWithKeys(fn ($m) => [$m->value => $m->label()])" :selected="$purchase->payment_method ?? 'cash'" />
                <div class="flex justify-between text-sm text-slate-600"><span>{{ __('Added to supplier balance') }}</span><span class="font-medium" data-balance>0.00</span></div>
                <button class="btn btn-primary w-full py-3">{{ $purchase->exists ? __('Save changes') : __('Save purchase') }}</button>
            </div>
        </div>
    </form>

    <script>
        // Show the product name next to the hidden label field for each added row.
        document.addEventListener('rows-changed', () => {
            document.querySelectorAll('[data-rows="items"] [data-row]').forEach((row) => {
                row.querySelector('[data-label-text]').textContent =
                    `${row.querySelector('[data-field="label"]').value}  ·  ${row.querySelector('[data-field="sku"]').value}`;
            });
        });
    </script>
</x-layouts.app>
