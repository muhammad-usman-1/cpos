<x-layouts.app :title="$product->name">
    <x-slot:actions>
        @can('products.manage')
            @barcodes<a href="{{ route('labels.create', ['variants' => $product->variants->pluck('id')->all()]) }}" class="btn btn-secondary">{{ __('Print labels') }}</a>@endbarcodes
            <a href="{{ route('products.edit', $product) }}" class="btn btn-primary">{{ __('Edit') }}</a>
        @endcan
        @can('records.delete')
            <form method="POST" action="{{ route('products.destroy', $product) }}" data-confirm="{{ __('Delete this product? It will be kept in history.') }}">
                @csrf @method('DELETE')
                <button class="btn btn-danger">{{ __('Delete') }}</button>
            </form>
        @endcan
    </x-slot:actions>

    @php($single = $product->variants->count() === 1 ? $product->variants->first() : null)

    <div class="card card-body">
        <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-3">
            <div><dt class="text-slate-500">{{ __('Barcode / code') }}</dt><dd class="font-mono">{{ $single?->sku ?? $product->code }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Quantity') }}</dt><dd @class(['text-lg font-semibold', 'text-rose-600' => $single?->isLowStock()])>{{ (float) $product->variants->sum('stock_qty') }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Status') }}</dt><dd>{!! $product->is_active ? '<span class="badge badge-green">'.e(__('Active')).'</span>' : '<span class="badge badge-gray">'.e(__('Inactive')).'</span>' !!}</dd></div>
            @can('cost.view')
                <div><dt class="text-slate-500">{{ __('Cost price') }}</dt><dd>{{ number_format($product->cost_price, 2) }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Average cost') }}</dt><dd>{{ number_format($product->average_cost, 2) }}</dd></div>
            @endcan
            <div><dt class="text-slate-500">{{ __('Sale price') }}</dt><dd class="font-medium">{{ number_format($product->sale_price, 2) }}</dd></div>
            @if ($single)
                <div><dt class="text-slate-500">{{ __('Low stock alert at') }}</dt><dd>{{ (float) $single->min_stock ?: '—' }}</dd></div>
            @endif
            <div><dt class="text-slate-500">{{ __('Added by') }}</dt><dd>{{ $product->creator?->name ?? '—' }} · {{ $product->created_at->translatedFormat('d M Y') }}</dd></div>
        </dl>
        @if ($product->description)
            <p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $product->description }}</p>
        @endif

        @if ($single)
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('stock.ledger', $single) }}" class="btn btn-secondary btn-sm">{{ __('Stock history') }}</a>
                @can('products.manage')
                    <a href="{{ route('products.edit', $product) }}" class="btn btn-secondary btn-sm">{{ __('Change quantity') }}</a>
                @endcan
            </div>
        @endif
    </div>

    @unless ($single)
        <div class="card mt-6 overflow-x-auto">
            <table class="table">
                <thead><tr><th>{{ __('Colour') }}</th><th>{{ __('Barcode / code') }}</th><th class="text-end">{{ __('Quantity') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($product->variants as $variant)
                    <tr>
                        <td class="font-medium">{{ $variant->colour?->name ?? __('Default') }}</td>
                        <td class="font-mono text-xs">{{ $variant->sku }}</td>
                        <td @class(['text-end font-semibold', 'text-rose-600' => $variant->isLowStock()])>{{ (float) $variant->stock_qty }}</td>
                        <td class="whitespace-nowrap text-end">
                            <a href="{{ route('stock.ledger', $variant) }}" class="btn btn-secondary btn-sm">{{ __('Stock history') }}</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endunless
</x-layouts.app>
