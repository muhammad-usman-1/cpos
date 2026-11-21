<x-layouts.app :title="$product->exists ? __('Edit product').': '.$product->name : __('Add product')">
    <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="card card-body space-y-5">
        @csrf
        @if ($product->exists)
            @method('PUT')
        @endif

        @php($hasColours = $product->exists && ! $variant)

        <div class="grid gap-4 sm:grid-cols-2">
            @unless ($hasColours)
                <x-field name="barcode" :label="__('Barcode / code')" :value="$variant?->sku" autocomplete="off"
                         :hint="__('Type your own code, or leave empty and one is made automatically.')" />
            @endunless
            <x-field name="name" :label="__('Name')" :value="$product->name" required autofocus />

            @unless ($hasColours)
                <x-field name="quantity" :label="$product->exists ? __('Quantity in stock') : __('Quantity')" type="number" step="1" min="0"
                         :value="$variant ? (float) $variant->stock_qty : null" required
                         :hint="$product->exists ? __('Changing it is saved in the stock history as a correction.') : null" />
            @endunless

            @can('cost.view')
                <x-field name="cost_price" :label="__('Cost price')" type="number" step="0.01" min="0" :value="$product->exists ? (float) $product->cost_price : null" :required="! $product->exists" />
            @endcan
            <x-field name="sale_price" :label="__('Sale price')" type="number" step="0.01" min="0" :value="$product->exists ? (float) $product->sale_price : null" required />

            @unless ($hasColours)
                <x-field name="min_stock" :label="__('Low stock alert at')" type="number" step="1" min="0" :value="$variant ? (float) $variant->min_stock : null"
                         :hint="__('Shown in Low stock when quantity falls to this number. Leave empty for no alert.')" />
            @endunless
        </div>

        @if ($hasColours)
            <div class="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm">
                <p class="mb-2 text-slate-600">{{ __('This older product has stock in several colours. Set the quantity of each colour here.') }}</p>
                <ul class="space-y-2">
                    @foreach ($product->variants as $item)
                        <li class="flex items-center justify-between gap-4">
                            <label for="quantity-{{ $item->id }}">{{ $item->colour?->name ?? __('Default') }} <span class="font-mono text-xs text-slate-500">{{ $item->sku }}</span></label>
                            <input id="quantity-{{ $item->id }}" name="quantities[{{ $item->id }}]" type="number" step="1" min="0" class="input w-28 text-end"
                                   value="{{ old('quantities.'.$item->id, (float) $item->stock_qty) }}">
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-field name="description" :label="__('Notes')" type="textarea" :value="$product->description" />

        @if ($product->exists)
            <x-checkbox name="is_active" :label="__('Active (available for purchase and sale)')" :checked="$product->is_active" />
        @endif

        <div class="flex gap-2">
            <button class="btn btn-primary">{{ __('Save product') }}</button>
            <a href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>

    @if ($created = session('product_created'))
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" data-product-created role="dialog" aria-modal="true" aria-labelledby="product-created-title">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 text-center shadow-xl">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><x-icon name="check" class="h-7 w-7" /></div>
                <h2 id="product-created-title" class="text-lg font-semibold">{{ __('Product created successfully.') }}</h2>
                <p class="mt-1 text-sm text-slate-600"><span class="font-medium text-slate-900">{{ $created['name'] }}</span>@if ($created['sku']) · <span class="font-mono">{{ $created['sku'] }}</span>@endif</p>
                <div class="mt-5 grid grid-cols-2 gap-2">
                    <a href="{{ route('products.index') }}" class="btn btn-secondary py-2.5">{{ __('Cancel') }}</a>
                    <button type="button" class="btn btn-primary py-2.5" data-create-another autofocus>{{ __('Add another product') }}</button>
                </div>
            </div>
        </div>
    @endif
</x-layouts.app>
