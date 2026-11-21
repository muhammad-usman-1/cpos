<x-print-layout :title="__('Labels')">
    <x-slot:head>
        <style>
            .label-sheet { display: flex; flex-wrap: wrap; gap: {{ $layout === 'sheet' ? '2mm' : '0' }}; padding: {{ $layout === 'sheet' ? '8mm' : '0' }}; }
            .barcode-label {
                width: {{ $size['width'] }}mm; height: {{ $size['height'] }}mm; box-sizing: border-box; padding: 1.2mm 1.5mm;
                overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; font-family: Arial, sans-serif;
                {{ $layout === 'sheet' ? 'border: 1px dashed #cbd5e1;' : '' }}
            }
            .barcode-label .name { font-size: {{ $size['height'] >= 30 ? '8pt' : '6.5pt' }}; font-weight: 700; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .barcode-label .meta { font-size: 6pt; line-height: 1.1; display: flex; justify-content: space-between; }
            .barcode-label .bars { flex: 1; min-height: 0; margin: 0.6mm 0; }
            .barcode-label .bars svg { width: 100%; height: 100%; }
            .barcode-label .code { font-size: 7pt; text-align: center; letter-spacing: 0.5px; line-height: 1; }
            .barcode-label .price { font-size: 8pt; font-weight: 700; }
            @media print {
                @page { size: {{ $layout === 'sheet' ? 'A4' : $size['width'].'mm '.$size['height'].'mm' }}; margin: 0; }
                .barcode-label { break-inside: avoid; {{ $layout === 'roll' ? 'break-after: page;' : '' }} }
            }
        </style>
    </x-slot:head>

    <div class="label-sheet">
        @forelse ($labels as $variant)
            <div class="barcode-label">
                <div class="name">{{ $variant->product->name }}</div>
                <div class="meta">
                    <span>{{ $variant->product->design_code }}{{ $variant->colour ? ' · '.$variant->colour->name : '' }}</span>
                    @if ($showPrice)<span class="price">{{ $currency }} {{ number_format($variant->product->sale_price, 0) }}</span>@endif
                </div>
                <div class="bars">{!! \App\Support\Code128::svg($variant->sku, 40) !!}</div>
                <div class="code">{{ $variant->sku }}</div>
            </div>
        @empty
            <p class="p-6 text-sm text-slate-500">{{ __('No labels to print. Set the number of copies above zero.') }}</p>
        @endforelse
    </div>
</x-print-layout>
