<x-print-layout :title="'Order slip '.$order->number">
    <x-slot:head>
        <style>@media print { @page { size: 80mm auto; margin: 3mm; } }</style>
    </x-slot:head>

    <div class="mx-auto p-4 text-sm" style="max-width: 80mm">
        <div class="text-center">
            <x-bill-logo style="display: block; margin: 0 auto 4px; max-height: 16mm; max-width: 55mm; object-fit: contain;" />
            <div class="text-lg font-bold">{{ $shopName }}</div>
            <x-shop-contact class="text-xs" />
            <div class="text-xs uppercase tracking-wide">{{ __('Stitching order · Customer copy') }}</div>
            @if ($copyNumber > 1)
                <div class="mt-1 text-xs font-bold uppercase">{{ __('Duplicate copy :number', ['number' => $copyNumber]) }}</div>
            @endif
        </div>
        <div class="my-3 text-center">
            @barcodes<div class="mx-auto h-12 w-56">{!! \App\Support\Code128::svg($order->number, 40) !!}</div>@endbarcodes
            <div class="font-mono text-base font-bold">{{ $order->number }}</div>
        </div>
        <table class="w-full">
            <tr><td class="text-slate-600">{{ __('Customer') }}</td><td class="text-end font-medium">{{ $order->customer->name }}</td></tr>
            <tr><td class="text-slate-600">{{ __('Phone') }}</td><td class="text-end">{{ $order->customer->phone }}</td></tr>
            <tr><td class="text-slate-600">{{ __('For') }}</td><td class="text-end">{{ $order->profile->label() }}</td></tr>
            <tr><td class="text-slate-600">{{ __('Pieces') }}</td><td class="text-end">{{ $order->pieces }}</td></tr>
            <tr><td class="text-slate-600">{{ __('Order date') }}</td><td class="text-end">{{ $order->order_date->translatedFormat('d M Y') }}</td></tr>
            <tr><td class="text-slate-600">{{ __('Delivery') }}</td><td class="text-end text-base font-bold">{{ $order->promised_date->translatedFormat('D d M Y') }}</td></tr>
        </table>
        <div class="my-2 border-t border-dashed border-slate-500"></div>
        <table class="w-full">
            <tr><td>{{ __('Stitching') }}</td><td class="text-end">{{ number_format($order->stitching_charges, 0) }}</td></tr>
            @foreach ($order->extras as $extra)
                <tr><td>{{ $extra->description }}</td><td class="text-end">{{ number_format($extra->amount, 0) }}</td></tr>
            @endforeach
            <tr class="font-bold"><td>{{ __('Total') }} {{ $currency }}</td><td class="text-end">{{ number_format($order->total, 0) }}</td></tr>
            <tr><td>{{ __('Advance paid') }}</td><td class="text-end">{{ number_format($order->paid_amount, 0) }}</td></tr>
            <tr class="text-base font-bold"><td>{{ __('Balance') }}</td><td class="text-end">{{ number_format($order->balanceDue(), 0) }}</td></tr>
        </table>
        <div class="my-2 border-t border-dashed border-slate-500"></div>
        <p class="mb-2 text-center text-sm font-bold">{{ __('Please bring this slip to collect your suit.') }}</p>
        <x-bill-terms setting="order_slip_terms" :title="__('Terms & conditions')" class="text-xs" />
        @if ($footer = \App\Models\Setting::get('receipt_footer'))
            <p class="mt-2 text-center text-xs">{{ $footer }}</p>
        @endif
        <p class="mt-2 text-center text-[10px] text-slate-500">{{ __('Printed :time', ['time' => now()->translatedFormat('d M Y h:i A')]) }}</p>
    </div>
</x-print-layout>
