<x-layouts.app :title="__('Low stock')">
    <x-slot:actions>
        @can('purchases.manage')
            <a href="{{ route('purchases.create') }}" class="btn btn-primary">{{ __('New purchase') }}</a>
        @endcan
    </x-slot:actions>

    <p class="mb-4 text-sm text-slate-600">{{ __('Colours at or below their minimum stock level. Set the minimum per colour on the product screen.') }}</p>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Barcode / code') }}</th><th class="text-end">{{ __('In stock') }}</th><th class="text-end">{{ __('Minimum') }}</th><th class="text-end">{{ __('Short by') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($variants as $variant)
                <tr>
                    <td><a href="{{ route('products.show', $variant->product) }}" class="font-medium text-indigo-600 hover:underline">{{ $variant->displayName() }}</a></td>
                    <td class="font-mono text-xs">{{ $variant->sku }}</td>
                    <td class="text-end font-semibold text-rose-600">{{ (float) $variant->stock_qty }}</td>
                    <td class="text-end">{{ (float) $variant->min_stock }}</td>
                    <td class="text-end">{{ (float) $variant->min_stock - (float) $variant->stock_qty }}</td>
                    <td class="text-end"><a href="{{ route('stock.ledger', $variant) }}" class="btn btn-secondary btn-sm">{{ __('Ledger') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">{{ __('No low stock items.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $variants->links() }}</div>
</x-layouts.app>
