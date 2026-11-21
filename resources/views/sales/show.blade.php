@php
    $whatsappText = 'Your receipt '.$sale->number.' from '.\App\Models\Setting::get('shop_name').': '.$shareUrl;
    $whatsappUrl = 'https://wa.me/'.($sale->customer ? $sale->customer->whatsappNumber() : '').'?text='.rawurlencode($whatsappText);
@endphp

<x-layouts.app :title="'Sale '.$sale->number">
    <x-slot:actions>
        <a href="{{ route('sales.receipt', [$sale, 'format' => '80mm']) }}" target="_blank" class="btn btn-primary">{{ __('Print receipt (80mm)') }}</a>
        <a href="{{ route('sales.receipt', [$sale, 'format' => 'a4']) }}" target="_blank" class="btn btn-secondary">{{ __('Print A4') }}</a>
        @messaging<a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-secondary">{{ __('Share on WhatsApp') }}</a>@endmessaging
        @can('sales.return')
            <a href="{{ route('sale-returns.create', $sale) }}" class="btn btn-secondary">{{ __('Return / exchange') }}</a>
        @endcan
        @if ($sale->customer)
            @can('orders.manage')
                <a href="{{ route('orders.create', ['customer' => $sale->customer_id, 'sale' => $sale->id]) }}" class="btn btn-secondary">{{ __('Stitch this cloth') }}</a>
            @endcan
        @endif
        @can('sales.create')
            <a href="{{ route('sales.create') }}" class="btn btn-secondary">{{ __('Next sale') }}</a>
        @endcan
    </x-slot:actions>

    @if (session('print_receipt'))
        <script>window.addEventListener('load', () => window.open(@json(route('sales.receipt', [$sale, 'format' => '80mm'])), '_blank'));</script>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card card-body lg:col-span-2">
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                <div><dt class="text-slate-500">{{ __('Customer') }}</dt><dd>
                    @if ($sale->customer)
                        <a href="{{ route('customers.show', $sale->customer) }}" class="text-indigo-600 hover:underline">{{ $sale->customer->name }}</a> · {{ $sale->customer->phone }}
                    @else
                        {{ __('Walk-in') }}
                    @endif
                </dd></div>
                <div><dt class="text-slate-500">{{ __('Date') }}</dt><dd>{{ $sale->completed_at?->translatedFormat('d M Y H:i') }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Salesman') }}</dt><dd>{{ $sale->creator?->name }}</dd></div>
            </dl>
            @if ($sale->notes)<p class="mt-3 text-sm">{{ $sale->notes }}</p>@endif
        </div>
        <div class="card card-body space-y-1 text-sm">
            <div class="flex justify-between"><span>{{ __('Items total') }}</span><span>{{ number_format($sale->subtotal, 2) }}</span></div>
            @if ((float) $sale->item_discount)<div class="flex justify-between"><span>{{ __('Item discounts') }}</span><span>− {{ number_format($sale->item_discount, 2) }}</span></div>@endif
            @if ((float) $sale->bill_discount)<div class="flex justify-between"><span>{{ __('Bill discount') }}</span><span>− {{ number_format($sale->bill_discount, 2) }}</span></div>@endif
            @if ((float) $sale->extra_charges)<div class="flex justify-between"><span>{{ __('Extra charges') }}</span><span>{{ number_format($sale->extra_charges, 2) }}</span></div>@endif
            <div class="flex justify-between border-t border-slate-200 pt-1 text-base font-semibold"><span>{{ __('Total') }}</span><span>{{ number_format($sale->total, 2) }}</span></div>
            @foreach ($sale->payments->where('direction', 'in') as $payment)
                <div class="flex justify-between text-slate-600"><span>{{ $payment->method->label() }}</span><span>{{ number_format($payment->amount, 2) }}</span></div>
            @endforeach
            @if ((float) $sale->credit_amount)<div class="flex justify-between font-medium text-rose-600"><span>{{ __('On credit') }}</span><span>{{ number_format($sale->credit_amount, 2) }}</span></div>@endif
        </div>
    </div>

    <div class="card mt-6 overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Item') }}</th><th>{{ __('SKU') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Returned') }}</th>@can('cost.view')<th class="text-end">{{ __('Cost') }}</th>@endcan</tr></thead>
            <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->variant->displayName() }}</td>
                    <td class="font-mono text-xs">{{ $item->variant->sku }}</td>
                    <td class="text-end">{{ (float) $item->quantity }}</td>
                    <td class="text-end">{{ number_format($item->price, 2) }}</td>
                    <td class="text-end">{{ (float) $item->discount ? number_format($item->discount, 2) : '' }}</td>
                    <td class="text-end">{{ number_format($item->amount, 2) }}</td>
                    <td class="text-end">{{ (float) $item->returned_qty ?: '' }}</td>
                    @can('cost.view')<td class="text-end">{{ number_format($item->unit_cost, 2) }}</td>@endcan
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if ($sale->returns->isNotEmpty() || $sale->stitchingOrders->isNotEmpty())
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            @if ($sale->returns->isNotEmpty())
                <div class="card card-body text-sm">
                    <h2 class="mb-2 font-semibold">{{ __('Returns') }}</h2>
                    @foreach ($sale->returns as $return)
                        <div><a href="{{ route('sale-returns.show', $return) }}" class="text-indigo-600 hover:underline">{{ $return->number }}</a> · {{ $return->return_date->translatedFormat('d M Y') }} · {{ number_format($return->total, 2) }} · {{ $return->refund_method->label() }}</div>
                    @endforeach
                </div>
            @endif
            @if ($sale->stitchingOrders->isNotEmpty())
                <div class="card card-body text-sm">
                    <h2 class="mb-2 font-semibold">{{ __('Stitching orders for this cloth') }}</h2>
                    @foreach ($sale->stitchingOrders as $order)
                        <div><a href="{{ route('orders.show', $order) }}" class="text-indigo-600 hover:underline">{{ $order->number }}</a> · {{ $order->status->label() }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</x-layouts.app>
