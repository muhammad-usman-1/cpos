@php
    $oldItem = $oldVariant ? [
        'id' => $oldVariant->id,
        'sku' => $oldVariant->sku,
        'label' => $oldVariant->displayName(),
        'code' => $oldVariant->product->code,
        'stock' => (float) $oldVariant->stock_qty,
        'sale_price' => (float) $oldVariant->product->sale_price,
    ] : null;
@endphp

<x-layouts.app :title="__('Barcode generator')">
    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Generator form. --}}
        <form method="POST" action="{{ route('barcode-labels.store') }}" class="card card-body space-y-5 xl:col-span-2" data-barcode-generator
              data-old-item="{{ $oldItem ? json_encode($oldItem) : '' }}">
            @csrf
            <input type="hidden" name="product_variant_id" value="{{ old('product_variant_id') }}" data-generator-variant>

            <div>
                <h2 class="font-semibold">{{ __('1. Choose the product') }}</h2>
                <p class="text-sm text-slate-500">{{ __('Search by name, product code, SKU or barcode. Any product can get labels, even with no stock.') }}</p>
            </div>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute start-4 top-1/2 h-6 w-6 -translate-y-1/2 text-slate-400" />
                <input type="search" class="input h-12 rounded-lg ps-13 text-base" placeholder="{{ __('Scan barcode or type name / code…') }}" autocomplete="off" autofocus
                       data-generator-search data-url="{{ route('products.lookup') }}">
                <div data-generator-results class="absolute z-20 mt-1 hidden max-h-80 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg [&>button]:py-2.5"></div>
                @error('product_variant_id')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="hidden rounded-lg border border-indigo-200 bg-indigo-50 p-4" data-generator-product>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-lg font-semibold" data-product-name></div>
                        <div class="text-sm text-slate-600">{{ __('SKU / barcode') }}: <span class="font-mono" data-product-sku></span> · {{ __('Code') }}: <span class="font-mono" data-product-code></span></div>
                    </div>
                    <div class="text-end text-sm">
                        <div class="text-slate-500">{{ __('Normal selling price') }}</div>
                        <div class="text-xl font-bold">{{ $currency }} <span data-product-price></span></div>
                        <div class="text-xs text-slate-500">{{ __('In stock') }}: <span data-product-stock></span></div>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="font-semibold">{{ __('2. Price and number of labels') }}</h2>
                <p class="text-sm text-slate-500">{{ __('This price is printed on the labels and used when they are scanned at the counter. The product\'s normal price does not change.') }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-field name="price" :label="__('Label selling price')" type="number" step="0.01" min="0" required data-generator-price />
                <x-field name="copies" :label="__('Number of labels')" type="number" step="1" min="1" max="1000" :value="old('copies', 1)" required />
                <x-select name="size" :label="__('Label size')" :options="collect($sizes)->mapWithKeys(fn ($size, $key) => [$key => $size['label']])" :selected="old('size', '50x25')" data-generator-size />
                <x-select name="layout" :label="__('Print on')" :options="['roll' => __('Label printer (roll)'), 'sheet' => __('A4 sheet')]" :selected="old('layout', 'roll')" />
            </div>
            <x-field name="notes" :label="__('Note (optional)')" :placeholder="__('e.g. Eid sale batch')" />

            <div class="flex flex-wrap items-center gap-3">
                <button class="btn btn-primary px-6 py-2.5 text-base">{{ __('Generate and preview') }}</button>
                <span class="text-xs text-slate-500">{{ __('You can check the labels on the next screen before printing.') }}</span>
            </div>
        </form>

        {{-- Live preview of one label. --}}
        <div class="card card-body space-y-3 self-start">
            <h2 class="font-semibold">{{ __('Label preview') }}</h2>
            <div class="flex justify-center rounded-lg bg-slate-100 p-6">
                <div class="flex flex-col justify-between overflow-hidden rounded border border-slate-300 bg-white p-1.5 font-sans text-black shadow-sm" style="width: 200px; height: 100px" data-preview-label>
                    <div class="truncate text-center text-[8px] leading-tight font-semibold uppercase tracking-wide">{{ $shopName }}</div>
                    <div class="truncate text-[11px] leading-tight font-bold" data-preview-name>{{ __('Product name') }}</div>
                    <div class="flex justify-between text-[9px] leading-tight">
                        <span class="truncate" data-preview-sku>SKU</span>
                        <span class="text-[11px] font-bold">{{ $currency }} <span data-preview-price>0</span></span>
                    </div>
                    <div class="my-0.5 min-h-0 flex-1 [&_svg]:h-full [&_svg]:w-full">{!! \App\Support\Code128::svg(\App\Models\BarcodeLabel::CODE_PREFIX.'0000000', 40) !!}</div>
                    <div class="text-center text-[9px] leading-none tracking-wide">{{ \App\Models\BarcodeLabel::CODE_PREFIX }}•••••••</div>
                </div>
            </div>
            <p class="text-xs text-slate-500">{{ __('Each batch gets its own code (PL…). The real code is printed after you generate.') }}</p>
        </div>
    </div>

    {{-- Labels made so far. --}}
    <div class="mt-8">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-lg font-semibold">{{ __('Price labels') }}</h2>
            <form method="GET" class="flex gap-2">
                <input type="search" name="q" value="{{ request('q') }}" class="input w-64" placeholder="{{ __('Search code or product') }}">
                <button class="btn btn-secondary">{{ __('Search') }}</button>
            </form>
        </div>
        <div class="card overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Label code') }}</th>
                        <th>{{ __('Product') }}</th>
                        <th class="text-end">{{ __('Label price') }}</th>
                        <th class="text-end">{{ __('Normal price') }}</th>
                        <th class="text-end">{{ __('Printed') }}</th>
                        <th class="text-end">{{ __('Sold') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Print more') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($labels as $label)
                    <tr @class(['opacity-60' => ! $label->is_active])>
                        <td class="whitespace-nowrap font-mono font-medium">{{ $label->code }}</td>
                        <td>
                            <a href="{{ route('products.show', $label->variant->product_id) }}" class="font-medium text-indigo-600 hover:underline">{{ $label->variant->displayName() }}</a>
                            <div class="text-xs text-slate-500">{{ $label->variant->sku }} · {{ $label->created_at->translatedFormat('d M Y') }}{{ $label->notes ? ' · '.$label->notes : '' }}</div>
                        </td>
                        <td class="text-end font-semibold">{{ number_format($label->price, 2) }}</td>
                        <td class="text-end text-slate-500">{{ number_format($label->variant->product->sale_price, 2) }}</td>
                        <td class="text-end">{{ $label->copies_printed }}</td>
                        <td class="text-end">{{ (float) $label->sold_qty }}</td>
                        <td><span class="badge {{ $label->is_active ? 'badge-green' : 'badge-gray' }}">{{ $label->is_active ? __('Active') : __('Stopped') }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('barcode-labels.reprint', $label) }}" class="flex items-center gap-1.5">
                                @csrf
                                <input type="number" name="copies" value="1" min="1" max="1000" class="input w-20 text-end" aria-label="{{ __('Number of labels') }}">
                                <select name="size" class="input w-28" aria-label="{{ __('Label size') }}">
                                    @foreach ($sizes as $key => $size)
                                        <option value="{{ $key }}" @selected($key === '50x25')>{{ $key }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="layout" value="roll">
                                <button class="btn btn-secondary btn-sm">{{ __('Print') }}</button>
                            </form>
                        </td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('barcode-labels.toggle', $label) }}">
                                @csrf @method('PATCH')
                                <button class="text-xs {{ $label->is_active ? 'text-rose-600' : 'text-emerald-700' }} hover:underline">{{ $label->is_active ? __('Stop') : __('Restart') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-8 text-center text-slate-500">{{ __('No price labels yet. Generate the first one above.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $labels->links() }}</div>
    </div>
</x-layouts.app>
