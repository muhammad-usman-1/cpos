@php
    $customerJson = $customer ? json_encode([
        'id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone, 'balance' => (float) $customer->balance,
        'tag' => $customer->tag?->label(), 'discount_percent' => (float) $customer->discount_percent,
    ]) : '';
    $saleMethods = collect($methods)->reject(fn ($method) => $method->value === 'cheque');
@endphp

<x-layouts.app :title="$held ? __('Resume held bill ').$held->number : __('New sale')" :pos="true">
    <form method="POST" action="{{ route('sales.store') }}" data-sale-form data-items="{{ json_encode($items) }}" data-customer="{{ $customerJson }}" class="h-full"
          data-offline-catalogue="{{ route('sales.offline.catalogue') }}" data-offline-token="{{ route('sales.offline.token') }}"
          data-shop-name="{{ \App\Models\Setting::get('shop_name') }}" data-currency="{{ \App\Models\Setting::get('currency') }}">
        @csrf
        {{-- Offline status: no internet, bills waiting to upload, bills the server did not accept. --}}
        <div class="mb-3 hidden space-y-1.5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm text-amber-900" data-offline-bar role="status" aria-live="polite"></div>
        <input type="hidden" name="held_id" value="{{ $held?->id }}">
        <input type="hidden" name="customer_id" value="{{ $customer?->id }}">

        <div data-customer-box class="flex h-full min-h-0 flex-col gap-3 lg:flex-row">
            {{-- Bill: search, total, details and the item lines. --}}
            <section class="flex min-h-[28rem] min-w-0 flex-1 flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-3 sm:flex-row sm:items-center md:p-4">
                    <div class="relative flex-1">
                        <x-icon name="search" class="pointer-events-none absolute start-4 top-1/2 h-6 w-6 -translate-y-1/2 text-slate-400" />
                        <input type="search" class="input h-14 rounded-lg ps-13 text-lg" placeholder="{{ __('Scan barcode or type name / code…') }}" autocomplete="off" autofocus
                               data-product-search data-url="{{ route('products.lookup', ['with_sales' => 1]) }}">
                        <div data-product-results class="absolute z-20 mt-1 hidden max-h-96 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white text-base shadow-lg [&>button]:py-2.5"></div>
                    </div>
                    <div class="rounded-xl bg-indigo-600 px-5 py-2 text-end text-white sm:min-w-64">
                        <div class="text-xs font-semibold uppercase tracking-widest text-indigo-100">{{ __('Total') }}</div>
                        <div class="text-5xl leading-none font-bold tabular-nums md:text-6xl" data-total>0.00</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-px border-b border-slate-200 bg-slate-200 text-sm md:grid-cols-5">
                    <div class="bg-indigo-50 px-4 py-2.5">
                        <div class="text-xs text-slate-500">{{ __('Bill') }}</div>
                        <div class="font-semibold">{{ $held?->number ?? __('New bill') }}</div>
                    </div>
                    <div class="bg-indigo-50 px-4 py-2.5">
                        <div class="text-xs text-slate-500">{{ __('Salesman') }}</div>
                        <div class="truncate font-semibold">{{ auth()->user()->name }}</div>
                    </div>
                    <div class="col-span-2 bg-indigo-50 px-4 py-2.5 md:col-span-1">
                        <div class="text-xs text-slate-500">{{ __('Customer') }}</div>
                        <div class="truncate font-semibold text-indigo-700" data-customer-name>{{ __('Walk-in customer') }}</div>
                    </div>
                    <div class="bg-indigo-50 px-4 py-2.5">
                        <div class="text-xs text-slate-500">{{ __('Current balance') }}</div>
                        <div class="font-semibold tabular-nums" data-customer-balance>0.00</div>
                    </div>
                    <div class="bg-indigo-50 px-4 py-2.5">
                        <div class="text-xs text-slate-500">{{ __('Items / quantity') }}</div>
                        <div class="font-semibold tabular-nums" data-item-count>0 / 0</div>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-auto">
                    <table class="table">
                        <thead class="sticky top-0 z-10 bg-indigo-600 text-white [&_th]:text-white">
                            <tr>
                                <th class="w-10">#</th>
                                <th>{{ __('Item') }}</th>
                                <th class="w-28 text-end">{{ __('Qty') }}</th>
                                <th class="w-32 text-end">{{ __('Price') }}</th>
                                <th class="w-28 text-end">{{ __('Discount %') }}</th>
                                <th class="w-32 text-end">{{ __('Amount') }}</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody data-rows="sale-items" class="sale-lines"></tbody>
                        <tbody><tr data-empty-row><td colspan="7" class="py-16 text-center text-slate-500">{{ __('Scan a barcode or search a product to start the bill.') }}</td></tr></tbody>
                    </table>
                    <template data-row-template="sale-items">
                        <tr data-row>
                            <td class="line-no text-slate-400"></td>
                            <td>
                                <input type="hidden" name="items[__INDEX__][product_variant_id]" data-field="product_variant_id">
                                <input type="hidden" name="items[__INDEX__][barcode_label_id]" data-field="barcode_label_id">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium" data-label-text></span>
                                    <span class="badge badge-amber hidden" data-price-label-badge>{{ __('Price label') }}</span>
                                </div>
                                <div class="text-xs text-slate-500" data-sku-text></div>
                                <div class="hidden text-xs font-medium text-rose-600" data-below-min>{{ __('Below minimum sale price') }}</div>
                            </td>
                            <td><input type="number" name="items[__INDEX__][quantity]" min="1" step="1" required class="input text-end" data-field="quantity"></td>
                            <td><input type="number" name="items[__INDEX__][price]" min="0" step="0.01" required class="input text-end" data-field="price"></td>
                            <td><input type="number" name="items[__INDEX__][discount_percent]" min="0" max="100" step="0.01" class="input text-end" placeholder="%" data-field="discount_percent"></td>
                            <td class="text-end font-semibold tabular-nums" data-amount>0.00</td>
                            <td><button type="button" class="rounded p-1 text-rose-600 hover:bg-rose-50" data-remove-row title="{{ __('Remove') }}"><x-icon name="x-mark" class="h-5 w-5" /></button></td>
                        </tr>
                    </template>
                </div>

                <div class="grid grid-cols-1 gap-px border-t border-slate-200 bg-slate-200 text-sm sm:grid-cols-3">
                    <div class="flex justify-between gap-2 bg-indigo-50 px-4 py-2.5"><span class="text-slate-500">{{ __('Items total') }}</span><span class="font-semibold tabular-nums" data-subtotal>0.00</span></div>
                    <div class="flex justify-between gap-2 bg-indigo-50 px-4 py-2.5"><span class="text-slate-500">{{ __('Item discounts') }}</span><span class="font-semibold tabular-nums">− <span data-item-discount>0.00</span></span></div>
                    <div class="flex justify-between gap-2 bg-indigo-50 px-4 py-2.5"><span class="text-slate-500">{{ __('Bill discount') }}</span><span class="font-semibold tabular-nums">− <span data-bill-discount-view>0.00</span></span></div>
                </div>
            </section>

            {{-- Right side: customer, payment and the counter buttons, all on screen. --}}
            <aside class="flex shrink-0 flex-col gap-3 lg:w-[23rem] lg:overflow-y-auto xl:w-[26rem]">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="label mb-0">{{ __('Customer') }} <span class="pos-key bg-indigo-100 text-indigo-700">F4</span></span>
                        <button type="button" class="hidden text-xs text-rose-600 hover:underline" data-clear-customer>{{ __('Remove') }}</button>
                    </div>
                    <div class="mb-2 text-xs text-slate-500" data-customer-meta></div>
                    <div class="grid grid-cols-2 gap-2" data-counter-customer data-url="{{ route('customers.lookup') }}" data-show-url="{{ route('customers.show', '__ID__') }}">
                        <div>
                            <label for="new_customer_phone" class="label">{{ __('Phone') }}</label>
                            <input id="new_customer_phone" name="new_customer_phone" type="tel" inputmode="tel" maxlength="16" autocomplete="off"
                                   value="{{ old('new_customer_phone') }}" class="input h-11 text-base tracking-wide" placeholder="03XXXXXXXXX" data-counter-phone>
                        </div>
                        <div>
                            <label for="new_customer_name" class="label">{{ __('Name') }}</label>
                            <input id="new_customer_name" name="new_customer_name" type="text" autocomplete="off"
                                   value="{{ old('new_customer_name') }}" class="input h-11 text-base" placeholder="{{ __('Customer name') }}" data-counter-name>
                        </div>
                    </div>
                    @error('new_customer_phone')<p class="error">{{ $message }}</p>@enderror
                    @error('new_customer_name')<p class="error">{{ $message }}</p>@enderror

                    {{-- Result of the phone check. --}}
                    <div class="mt-2 hidden items-center justify-between gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" data-counter-found>
                        <span class="flex min-w-0 items-center gap-1.5"><x-icon name="check" class="h-4 w-4 shrink-0" /><span class="truncate" data-counter-found-text></span></span>
                        <a href="#" class="btn btn-sm shrink-0 bg-emerald-600 text-white hover:bg-emerald-700" data-counter-details>{{ __('More details') }}</a>
                    </div>
                    <div class="mt-2 hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800" data-counter-new>
                        {{ __('New customer. Enter the name; the customer is saved with this bill.') }}
                    </div>
                    <div class="mt-2 hidden text-sm text-slate-500" data-counter-checking>{{ __('Checking…') }}</div>
                </div>

                <div class="space-y-3 rounded-xl bg-white p-4 text-sm shadow-sm ring-1 ring-slate-200" data-payment-panel>
                    <div>
                            <label for="bill_discount_percent" class="label">{{ __('Bill discount %') }}</label>
                            <input id="bill_discount_percent" type="number" step="0.01" min="0" max="100" name="bill_discount_percent" value="{{ old('bill_discount_percent', $heldBillDiscountPercent) }}" class="input text-end" placeholder="%">
                            @error('bill_discount_percent')<p class="error">{{ $message }}</p>@enderror
                            @error('bill_discount')<p class="error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="label mb-0">{{ __('Payment') }} <span class="pos-key bg-indigo-100 text-indigo-700">F8</span></span>
                            <button type="button" class="text-xs text-indigo-600 hover:underline" data-add-payment>{{ __('+ Split payment') }}</button>
                        </div>
                        <div data-rows="payments" class="space-y-2">
                            <div data-row class="flex gap-2">
                                <select name="payments[0][method]" class="input w-32" data-field="method">
                                    @foreach ($saleMethods as $method)
                                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                                <input type="number" step="0.01" min="0" name="payments[0][amount]" class="input text-end text-base" data-field="amount" placeholder="{{ __('Amount') }}">
                            </div>
                        </div>
                        <template data-row-template="payments">
                            <div data-row class="flex gap-2">
                                <select name="payments[__INDEX__][method]" class="input w-32" data-field="method">
                                    @foreach ($saleMethods as $method)
                                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                                <input type="number" step="0.01" min="0" name="payments[__INDEX__][amount]" class="input text-end" data-field="amount" placeholder="{{ __('Amount') }}">
                                <input type="text" name="payments[__INDEX__][reference_no]" class="input w-24" placeholder="{{ __('Ref no.') }}">
                                <button type="button" class="text-rose-600" data-remove-row><x-icon name="x-mark" class="h-5 w-5" /></button>
                            </div>
                        </template>
                    </div>


                    <div class="grid grid-cols-2 items-end gap-3">
                        <div>
                            <label class="label" for="cash-received">{{ __('Cash received') }}</label>
                            <input id="cash-received" name="cash_received" type="number" step="1" min="0" class="input text-end text-base" data-tendered placeholder="{{ __('Optional') }}">
                        </div>
                        <div class="rounded-lg bg-emerald-50 px-3 py-2 text-end">
                            <div class="text-xs text-emerald-800">{{ __('Change to return') }}</div>
                            <div class="text-2xl font-bold tabular-nums text-emerald-700" data-change>0.00</div>
                        </div>
                    </div>

                    <x-field name="notes" :label="__('Note on bill')" :value="$held?->notes" />
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <button name="action" value="hold" class="pos-tile pos-tile-amber min-h-16" formnovalidate>
                        <x-icon name="pause" class="h-6 w-6" />
                        <span>{{ __('Hold bill') }}</span>
                    </button>
                    <button name="action" value="complete" class="pos-tile pos-tile-green col-span-2 min-h-16 text-base" data-complete-sale>
                        <x-icon name="check" class="h-7 w-7" />
                        <span>{{ __('Complete sale') }}</span>
                    </button>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <button type="button" class="pos-tile relative min-h-20" data-modal-open="held">
                        <x-icon name="clipboard" class="h-6 w-6" />
                        <span>{{ __('Held bills') }}</span>
                        @if ($heldSales->isNotEmpty())
                            <span class="absolute end-1.5 top-1.5 rounded-full bg-amber-400 px-2 text-xs font-bold text-slate-900">{{ $heldSales->count() }}</span>
                        @endif
                    </button>
                    <a href="{{ route('sales.create') }}" class="pos-tile min-h-20" data-new-bill>
                        <x-icon name="plus" class="h-6 w-6" />
                        <span>{{ __('New bill') }}</span>
                    </a>
                    <button type="button" class="pos-tile pos-tile-rose min-h-20" data-clear-items>
                        <x-icon name="trash" class="h-6 w-6" />
                        <span>{{ __('Clear items') }}</span>
                    </button>
                </div>
                @if ($held)
                    <button form="discard-held" class="w-full text-center text-xs text-rose-600 hover:underline">{{ __('Discard this held bill') }}</button>
                @endif
            </aside>
        </div>

        {{-- Held bills. --}}
        <div class="pos-modal" data-modal="held" role="dialog" aria-modal="true" aria-labelledby="held-title">
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 id="held-title" class="text-lg font-semibold">{{ __('Held bills') }}</h2>
                    <button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" data-modal-close aria-label="{{ __('Close') }}"><x-icon name="x-mark" class="h-6 w-6" /></button>
                </div>
                <div class="max-h-[60vh] overflow-y-auto">
                    <table class="table">
                        <tbody>
                        @forelse ($heldSales as $heldSale)
                            <tr @class(['bg-indigo-50' => $held?->id === $heldSale->id])>
                                <td class="font-medium">{{ $heldSale->number }}</td>
                                <td>{{ $heldSale->customer?->name ?? __('Walk-in') }}</td>
                                <td class="text-xs text-slate-500">{{ $heldSale->updated_at->diffForHumans() }}</td>
                                <td class="text-end tabular-nums">{{ number_format($heldSale->total, 2) }}</td>
                                <td class="text-end"><a href="{{ route('sales.create', ['resume' => $heldSale->id]) }}" class="btn btn-primary btn-sm">{{ __('Resume') }}</a></td>
                            </tr>
                        @empty
                            <tr><td class="py-10 text-center text-slate-500">{{ __('No held bills.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>

    @if ($held)
        <form id="discard-held" method="POST" action="{{ route('sales.discard', $held) }}" data-confirm="Discard held bill {{ $held->number }}?">
            @csrf @method('DELETE')
        </form>
    @endif

    @if ($completedSale = session('completed_sale'))
        <div class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4" data-sale-done
             data-receipt-url="{{ $completedSale['receipt_url'] }}" role="dialog" aria-modal="true" aria-labelledby="sale-done-title">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 text-center shadow-xl">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><x-icon name="check" class="h-7 w-7" /></div>
                <h2 id="sale-done-title" class="text-lg font-semibold">{{ __('Sale :number completed.', ['number' => $completedSale['number']]) }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('Total') }}: <span class="font-semibold text-slate-900">{{ number_format($completedSale['total'], 2) }}</span></p>
                <div class="mt-5 grid grid-cols-2 gap-2">
                    <a href="{{ $completedSale['show_url'] }}" class="btn btn-secondary py-2.5">{{ __('Show this sale') }}</a>
                    <button type="button" class="btn btn-primary py-2.5" data-sale-done-close>{{ __('Close') }}</button>
                </div>
                <button type="button" class="mt-3 text-sm text-indigo-600 hover:underline" data-sale-done-print>{{ __('Print again') }}</button>
            </div>
        </div>
    @endif
</x-layouts.app>
