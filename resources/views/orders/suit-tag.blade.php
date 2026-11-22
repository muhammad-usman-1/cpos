{{-- Tag tied to the suit (80mm thermal): who it belongs to, when it is due and the measurements. No money is shown. --}}
<x-print-layout :title="__('Suit tag').' '.$order->number">
    <x-slot:head>
        <style>@media print { @page { size: 80mm auto; margin: 3mm; } }</style>
    </x-slot:head>

    <div class="mx-auto p-3 text-sm" style="max-width: 80mm">
        <div class="flex items-center justify-between border-b-2 border-slate-800 pb-1">
            <div class="font-bold">{{ $shopName }}</div>
            <div class="text-xs uppercase tracking-wide">{{ __('Suit tag') }}@if ($copyNumber > 1) · {{ __('Copy :number', ['number' => $copyNumber]) }}@endif</div>
        </div>

        <div class="my-2 text-center">
            @barcodes<div class="mx-auto h-10 w-56">{!! \App\Support\Code128::svg($order->number, 36) !!}</div>@endbarcodes
            <div class="font-mono text-lg font-bold">{{ $order->number }}</div>
        </div>

        <table class="w-full">
            <tr><td class="text-slate-600">{{ __('Customer') }}</td><td class="text-end text-base font-bold">{{ $order->customer->name }}</td></tr>
            <tr><td class="text-slate-600">{{ __('Phone') }}</td><td class="text-end">{{ $order->customer->phone }}</td></tr>
            <tr><td class="text-slate-600">{{ __('For') }}</td><td class="text-end">{{ $order->profile->label() }} ({{ $order->profile->template->label() }})</td></tr>
            <tr><td class="text-slate-600">{{ __('Pieces') }}</td><td class="text-end">{{ $order->pieces }}</td></tr>
            <tr><td class="text-slate-600">{{ __('Order date') }}</td><td class="text-end">{{ $order->order_date->translatedFormat('d M Y') }}</td></tr>
            <tr><td class="text-slate-600">{{ __('Delivery') }}</td><td class="text-end text-base font-bold">{{ $order->promised_date->translatedFormat('D d M Y') }}</td></tr>
            <tr><td class="text-slate-600">{{ __('Tailor') }}</td><td class="text-end">{{ $order->tailor?->name ?? '________' }}</td></tr>
        </table>

        @if ($order->design_notes || $order->cloth_description)
            <div class="mt-2 border border-slate-500 p-1.5 text-xs">
                @if ($order->design_notes)
                    <div class="whitespace-pre-line"><span class="font-semibold">{{ __('Design:') }}</span> {{ $order->design_notes }}</div>
                @endif
                @if ($order->cloth_description)
                    <div>{{ __('Cloth:') }} {{ $order->cloth_description }}{{ $order->cloth_quantity ? ' · '.(float) $order->cloth_quantity.' m' : '' }}</div>
                @endif
            </div>
        @endif

        @if ($measurement)
            @foreach (['body' => __('Measurements'), 'style' => __('Style')] as $group => $heading)
                @php($groupValues = $measurement->values->filter(fn ($value) => $value->field?->group === $group)->sortBy(fn ($value) => $value->field->sort_order))
                @if ($groupValues->isNotEmpty())
                    <div class="mt-2 flex justify-between text-xs font-semibold uppercase">
                        <span>{{ $heading }}</span>
                        @if ($group === 'body')<span class="normal-case">{{ $measurement->unitLabel() }} · v{{ $measurement->version }}</span>@endif
                    </div>
                    <table class="w-full border-collapse text-xs">
                        @foreach ($groupValues->chunk(2) as $pair)
                            <tr>
                                @foreach ($pair as $value)
                                    <td class="border border-slate-500 px-1 py-0.5">{{ __($value->field->label) }}</td>
                                    <td class="border border-slate-500 px-1 py-0.5 text-end text-sm font-bold">{{ __($value->value) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </table>
                @endif
            @endforeach
            @if ($measurement->notes)
                <div class="mt-2 border border-slate-500 p-1.5 text-xs"><span class="font-semibold">{{ __('Instructions:') }}</span> {{ $measurement->notes }}</div>
            @endif
        @else
            <p class="mt-2 border border-slate-500 p-1.5 text-xs">{{ __('No measurement attached to this order.') }}</p>
        @endif
    </div>
</x-print-layout>
