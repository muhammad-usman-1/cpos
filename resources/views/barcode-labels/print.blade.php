<x-print-layout :title="__('Price labels').' '.$label->code">
    <x-slot:head>
        <style>
            .label-info { padding: 10px 24px; font: 13px Arial, sans-serif; color: #334155; }
            .label-sheet { display: flex; flex-wrap: wrap; gap: {{ $layout === 'sheet' ? '2mm' : '4mm' }}; padding: {{ $layout === 'sheet' ? '8mm' : '6mm' }}; }
            .barcode-label {
                width: {{ $size['width'] }}mm; height: {{ $size['height'] }}mm; box-sizing: border-box; padding: 1.2mm 1.8mm;
                overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; font-family: Arial, sans-serif; color: #000; background: #fff;
                border: 1px dashed #cbd5e1;
            }
            .barcode-label .shop { font-size: {{ $size['height'] >= 30 ? '6.5pt' : '5.5pt' }}; font-weight: 600; text-align: center; text-transform: uppercase; letter-spacing: 0.4px; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .barcode-label .name { font-size: {{ $size['height'] >= 30 ? '8.5pt' : '7pt' }}; font-weight: 700; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .barcode-label .meta { font-size: 6.5pt; line-height: 1.15; display: flex; justify-content: space-between; align-items: baseline; gap: 1mm; }
            .barcode-label .meta span:first-child { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
            .barcode-label .price { font-size: {{ $size['height'] >= 30 ? '10pt' : '8.5pt' }}; font-weight: 700; white-space: nowrap; }
            .barcode-label .bars { flex: 1; min-height: 0; margin: 0.6mm 0; }
            .barcode-label .bars svg { width: 100%; height: 100%; display: block; }
            .barcode-label .code { font-size: 7pt; text-align: center; letter-spacing: 0.6px; line-height: 1; }
            @media print {
                .label-info { display: none; }
                .label-sheet { padding: {{ $layout === 'sheet' ? '8mm' : '0' }}; gap: {{ $layout === 'sheet' ? '2mm' : '0' }}; }
                .barcode-label { break-inside: avoid; {{ $layout === 'sheet' ? '' : 'border: 0; break-after: page;' }} }
                @page { size: {{ $layout === 'sheet' ? 'A4' : $size['width'].'mm '.$size['height'].'mm' }}; margin: 0; }
            }
        </style>
    </x-slot:head>

    <div class="label-info">
        <strong>{{ $copies }}</strong> {{ __('labels') }} · {{ $label->variant->displayName() }} · {{ __('Label price') }} <strong>{{ $currency }} {{ number_format($label->price, 2) }}</strong>
        ({{ __('normal price') }} {{ number_format($label->variant->product->sale_price, 2) }}) · {{ __('Code') }} <strong>{{ $label->code }}</strong> · {{ $size['label'] }}
        <a href="{{ route('barcode-labels.index') }}" style="margin-inline-start: 12px; color: #475569">{{ __('Back to barcode generator') }}</a>
    </div>

    @php
        $bars = \App\Support\Code128::svg($label->code, 40);
    @endphp
    <div class="label-sheet">
        @for ($i = 0; $i < $copies; $i++)
            <div class="barcode-label">
                <div class="shop">{{ $shopName }}</div>
                <div class="name">{{ $label->variant->product->name }}</div>
                <div class="meta">
                    <span>{{ $label->variant->sku }}{{ $label->variant->colour ? ' · '.$label->variant->colour->name : '' }}</span>
                    <span class="price">{{ $currency }} {{ number_format($label->price, 0) }}</span>
                </div>
                <div class="bars">{!! $bars !!}</div>
                <div class="code">{{ $label->code }}</div>
            </div>
        @endfor
    </div>
</x-print-layout>
