<x-layouts.app :title="'Sale return '.$return->number">
    <x-slot:actions>
        <button type="button" onclick="window.print()" class="btn btn-secondary">{{ __('Print') }}</button>
        <a href="{{ route('sales.show', $return->sale) }}" class="btn btn-secondary">{{ __('Back to') }} {{ $return->sale->number }}</a>
        @can('sales.create')
            <a href="{{ route('sales.create', ['customer' => $return->customer_id]) }}" class="btn btn-primary">{{ __('Exchange: new sale') }}</a>
        @endcan
    </x-slot:actions>

    {{-- Shown only on paper. --}}
    <div class="mb-4 hidden items-center gap-4 border-b border-slate-300 pb-3 print:flex">
        <x-bill-logo style="display: block; max-height: 16mm; max-width: 50mm; object-fit: contain;" />
        <div>
            <div class="text-lg font-bold">{{ \App\Models\Setting::get('shop_name') }}</div>
            <x-shop-contact class="text-sm" />
            <div class="text-sm">{{ __('Sale return') }} {{ $return->number }}</div>
        </div>
    </div>

    <div class="card card-body mb-6">
        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-5">
            <div><dt class="text-slate-500">{{ __('Customer') }}</dt><dd>{{ $return->customer?->name ?? __('Walk-in') }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Date') }}</dt><dd>{{ $return->return_date->translatedFormat('d M Y') }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Against sale') }}</dt><dd>{{ $return->sale->number }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Refund') }}</dt><dd>{{ $return->refund_method->label() }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Entered by') }}</dt><dd>{{ $return->creator?->name }}</dd></div>
        </dl>
        @if ($return->notes)<p class="mt-3 text-sm">{{ $return->notes }}</p>@endif
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Item') }}</th><th>{{ __('SKU') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
            <tbody>
            @foreach ($return->items as $item)
                <tr>
                    <td>{{ $item->variant->displayName() }}</td>
                    <td class="font-mono text-xs">{{ $item->variant->sku }}</td>
                    <td class="text-end">{{ (float) $item->quantity }}</td>
                    <td class="text-end">{{ number_format($item->price, 2) }}</td>
                    <td class="text-end">{{ number_format($item->amount, 2) }}</td>
                </tr>
            @endforeach
            <tr class="font-semibold"><td colspan="4">{{ __('Total refunded') }}</td><td class="text-end">{{ number_format($return->total, 2) }}</td></tr>
            </tbody>
        </table>
    </div>
</x-layouts.app>
