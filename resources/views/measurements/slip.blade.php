{{-- Tailor's measurement slip (MSR08). $order is optional; without it the slip shows only the customer and profile. --}}
<x-print-layout :title="__('Measurement slip').($order ? ' '.$order->number : '')">
    <x-slot:head>
        <style>@media print { @page { size: A5; margin: 8mm; } }</style>
    </x-slot:head>

    <div class="mx-auto max-w-2xl p-6 text-sm">
        <div class="flex items-start justify-between border-b-2 border-slate-800 pb-2">
            <div>
                <x-bill-logo style="display: block; margin-bottom: 4px; max-height: 14mm; max-width: 50mm; object-fit: contain;" />
                <div class="text-lg font-bold">{{ $shopName }}</div>
                <x-shop-contact class="text-xs" />
                <div class="text-xs uppercase tracking-wide">{{ __('Measurement slip · Tailor copy') }}</div>
            </div>
            @if ($order)
                <div class="w-48 text-center">
                    @barcodes<div class="h-10">{!! \App\Support\Code128::svg($order->number, 40) !!}</div>@endbarcodes
                    <div class="font-mono text-base font-bold">{{ $order->number }}</div>
                </div>
            @endif
        </div>

        @php($profile = $order?->profile ?? $measurement?->profile)
        <div class="my-3 grid grid-cols-2 gap-x-6 gap-y-1">
            <div><span class="text-slate-500">{{ __('Customer:') }}</span> <strong>{{ ($order?->customer ?? $profile?->customer)?->name }}</strong></div>
            <div><span class="text-slate-500">{{ __('For:') }}</span> <strong>{{ $profile?->label() }}</strong> ({{ $profile?->template->label() }})</div>
            @if ($order)
                <div><span class="text-slate-500">{{ __('Order date:') }}</span> {{ $order->order_date->translatedFormat('d M Y') }}</div>
                <div><span class="text-slate-500">{{ __('Delivery:') }}</span> <strong>{{ $order->promised_date->translatedFormat('D d M Y') }}</strong></div>
                <div><span class="text-slate-500">{{ __('Pieces:') }}</span> {{ $order->pieces }}</div>
                <div><span class="text-slate-500">{{ __('Tailor:') }}</span> {{ $order->tailor?->name ?? '________' }}</div>
            @endif
            @if ($measurement)
                <div><span class="text-slate-500">{{ __('Measurement:') }}</span> v{{ $measurement->version }}, {{ $measurement->created_at->translatedFormat('d M Y') }} ({{ $measurement->unitLabel() }})</div>
            @endif
        </div>

        @if ($order?->design_notes || $order?->cloth_description)
            <div class="mb-3 rounded border border-slate-400 p-2">
                <div class="text-xs font-semibold uppercase">{{ __('Design') }}</div>
                <div class="whitespace-pre-line">{{ $order->design_notes }}</div>
                @if ($order->cloth_description)
                    <div class="mt-1 text-xs">{{ __('Cloth:') }} {{ $order->cloth_description }}{{ $order->cloth_quantity ? ' · '.(float) $order->cloth_quantity.' m' : '' }}</div>
                @endif
            </div>
        @endif

        @if ($measurement)
            @foreach (['body' => __('Measurements'), 'style' => __('Style')] as $group => $heading)
                @php($groupValues = $measurement->values->filter(fn ($value) => $value->field?->group === $group)->sortBy(fn ($value) => $value->field->sort_order))
                @if ($groupValues->isNotEmpty())
                    <div class="mb-1 mt-3 text-xs font-semibold uppercase">{{ $heading }}</div>
                    <table class="w-full border-collapse">
                        @foreach ($groupValues->chunk(2) as $pair)
                            <tr>
                                @foreach ($pair as $value)
                                    <td class="border border-slate-400 px-2 py-1">{{ __($value->field->label) }}</td>
                                    <td class="w-20 border border-slate-400 px-2 py-1 text-end text-base font-bold">{{ __($value->value) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </table>
                @endif
            @endforeach
            @if ($measurement->notes)
                <div class="mt-3 rounded border border-slate-400 p-2"><span class="text-xs font-semibold uppercase">{{ __('Instructions:') }}</span> {{ $measurement->notes }}</div>
            @endif
        @else
            <p class="mt-4 rounded border border-amber-400 p-2">{{ __('No measurement attached to this order.') }}</p>
        @endif
    </div>
</x-print-layout>
