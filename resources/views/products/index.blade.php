<x-layouts.app :title="__('Products')">
    <x-slot:actions>
        @can('products.import')
            <a href="{{ route('products.import') }}" class="btn btn-secondary">{{ __('Import CSV') }}</a>
        @endcan
        @can('products.manage')
            <a href="{{ route('products.create') }}" class="btn btn-primary">{{ __('Add product') }}</a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-64 flex-1">
            <label class="label" for="q">{{ __('Search') }}</label>
            <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Name or barcode / code') }}" class="input" autofocus>
        </div>
        <x-select name="status" :label="__('Status')" :options="['active' => __('Active'), 'inactive' => __('Inactive'), 'low' => __('Low stock')]" :selected="request('status')" :placeholder="__('All')" class="w-44" />
        <button class="btn btn-secondary">{{ __('Search') }}</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
            <tr>
                <th>{{ __('Barcode / code') }}</th><th>{{ __('Name') }}</th>
                <th class="text-end">{{ __('Quantity') }}</th>
                @can('cost.view')<th class="text-end">{{ __('Cost price') }}</th>@endcan
                <th class="text-end">{{ __('Sale price') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($products as $product)
                @php($lowStock = $product->variants->contains(fn ($variant) => $variant->isLowStock()))
                <tr data-href="{{ route('products.show', $product) }}">
                    <td class="whitespace-nowrap font-mono text-xs">{{ $product->variants->count() === 1 ? $product->variants->first()->sku : $product->code }}</td>
                    <td>
                        <a href="{{ route('products.show', $product) }}" class="font-medium text-indigo-600 hover:underline">{{ $product->name }}</a>
                        @unless ($product->is_active)<span class="badge badge-gray">{{ __('Inactive') }}</span>@endunless
                        @if ($product->variants->count() > 1)
                            <div class="text-xs text-slate-500">{{ $product->variants->map(fn ($v) => ($v->colour?->name ?? __('Default')).': '.(float) $v->stock_qty)->implode(' · ') }}</div>
                        @endif
                    </td>
                    <td @class(['text-end font-medium', 'text-rose-600' => $lowStock])>{{ (float) $product->total_stock }}</td>
                    @can('cost.view')<td class="text-end">{{ number_format($product->cost_price, 2) }}</td>@endcan
                    <td class="text-end">{{ number_format($product->sale_price, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-slate-500">{{ __('No products found.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
</x-layouts.app>
