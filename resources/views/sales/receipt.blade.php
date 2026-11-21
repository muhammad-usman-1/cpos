@php
    $thermal = $format === '80mm';
    $isUrdu = app()->getLocale() === 'ur';
    $itemLines = $sale->items->count();
    $pieces = (float) $sale->items->sum('quantity');
    $saved = round((float) $sale->item_discount + (float) $sale->bill_discount, 2);
    $policies = \App\Models\Setting::lines('receipt_policies');
    $headerNote = \App\Models\Setting::get('receipt_header_note');
    $payments = $sale->payments->where('direction', 'in')->whereNull('cancelled_at');
    $hasLogo = (bool) \App\Models\Setting::billLogoUrl();
    // Cash kept on the bill, and the cash the customer handed over (same amount when it was not entered).
    $cashKept = round((float) $payments->filter(fn ($payment) => $payment->method->value === 'cash')->sum('amount'), 2);
    $cashGiven = max((float) ($sale->cash_received ?? 0), $cashKept);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isUrdu ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Receipt') }} {{ $sale->number }}</title>
    @include('partials.locale-head')
    <style>
        body { font-family: {!! $isUrdu ? "'Noto Naskh Arabic', Tahoma, sans-serif" : "'Inconsolata', 'Courier New', Courier, monospace" !!}; font-stretch: 87.5%; font-weight: 400; color: #111; margin: 0; background: #fff; }
        .receipt { width: {{ $thermal ? '74mm' : '150mm' }}; max-width: 100%; margin: 0 auto; padding: {{ $thermal ? '4mm 2.5mm 10mm' : '12mm' }}; box-sizing: border-box; font-size: {{ $thermal ? '13.5px' : '16px' }}; line-height: 1.5; }
        .center { text-align: center; } .right { text-align: end; } .bold { font-weight: 800; }
        .logo { display: block; margin: 0 auto 10px; max-height: {{ $thermal ? '18mm' : '24mm' }}; max-width: {{ $thermal ? '60mm' : '80mm' }}; object-fit: contain; }
        .shop-name { font-size: 1.3em; font-weight: 800; text-transform: uppercase; margin-bottom: 2px; }
        .contact { margin-bottom: 8px; text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 1px 0; vertical-align: top; font-weight: inherit; }
        .dash { border-top: 1px dashed #111; margin: 6px 0; }
        .solid { border-top: 1.5px solid #111; margin: 10px 0 8px; }
        .gap { height: 10px; }
        .info td:nth-child(odd) { white-space: nowrap; padding-inline-end: 4px; }
        .info td:nth-child(even) { padding-inline-end: 10px; }
        .items th { text-align: end; font-weight: 600; padding: 3px 0; border-top: 1px dashed #111; border-bottom: 1px dashed #111; white-space: nowrap; }
        .items th:first-child, .items td:first-child { text-align: start; }
        .items td { text-align: end; padding-top: 6px; white-space: nowrap; }
        .items .desc td { padding: 0 0 2px; white-space: normal; }
        .items .sub td { padding: 0 0 6px; }
        .items .sub td:first-child { padding-inline-start: 12px; }
        .totals td { padding: 2px 0; }
        .totals td:last-child { text-align: end; white-space: nowrap; }
        .totals .net td { font-weight: 800; font-size: 1.12em; padding: 5px 0; }
        .note-box { margin: 8px 0; padding: 4px 6px; border: 1px solid #111; font-weight: 600; text-align: center; }
        .policy-title { font-size: 1.45em; font-weight: 800; text-transform: uppercase; letter-spacing: 0.3px; margin: 0 0 6px; }
        .policy { margin: 0; padding: 0; list-style: none; }
        .policy li { margin-bottom: 5px; }
        .barcode svg { width: 90%; height: {{ $thermal ? '46px' : '52px' }}; }
        .toolbar { padding: 10px; background: #f1f5f9; text-align: center; font-family: Arial, sans-serif; }
        .toolbar button, .toolbar a { margin: 0 4px; padding: 6px 14px; font-size: 14px; }
        @media print { .toolbar { display: none; } @page { {{ $thermal ? 'size: 80mm auto; margin: 0;' : 'size: A4; margin: 10mm;' }} } }
    </style>
</head>
<body>
    @if ($showToolbar)
        <div class="toolbar">
            <button type="button" onclick="window.print()">{{ __('Print') }}</button>
            <a href="{{ route('sales.receipt', [$sale, 'format' => $thermal ? 'a4' : '80mm']) }}">{{ __('Switch to') }} {{ $thermal ? 'A4' : '80mm' }}</a>
            <span style="font-size:12px;color:#475569">{{ __('For a PDF, choose "Save as PDF" in the print dialog.') }}</span>
        </div>
    @endif

    <div class="receipt">
        {{-- Shop: the logo carries the name; the name is printed only when no bill logo is uploaded. --}}
        <x-bill-logo class="logo" style="" />
        @unless ($hasLogo)
            <div class="shop-name">{{ $shopName }}</div>
        @endunless
        <x-shop-contact class="contact" />
        @if ($headerNote)
            <div class="note-box">{{ $headerNote }}</div>
        @endif

        {{-- Bill details --}}
        <table class="info">
            <tr>
                <td>{{ __('Cashier') }} :</td><td>{{ $sale->creator?->name }}</td>
                <td>{{ __('Invoice') }} :</td><td class="bold">{{ $sale->number }}</td>
            </tr>
            <tr>
                <td>{{ __('Date') }} :</td><td>{{ $sale->completed_at?->format('n/j/Y') }}</td>
                <td>{{ __('Time') }} :</td><td>{{ $sale->completed_at?->format('h:i A') }}</td>
            </tr>
        </table>
        <div>{{ __('Customer') }} : {{ $sale->customer ? $sale->customer->name.' · '.$sale->customer->phone : __('Walk-in') }}</div>

        {{-- Items: code with figures, then the description and the price line under it --}}
        <table class="items" style="margin-top: 6px;">
            <thead>
                <tr>
                    <th>{{ __('ITEM CODE') }}</th>
                    <th>{{ __('QTY') }}</th>
                    <th>{{ __('PRICE') }}</th>
                    <th>{{ __('TOTAL') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->variant->sku }}</td>
                    <td>{{ (float) $item->quantity }}</td>
                    <td>{{ number_format($item->price, 0) }}</td>
                    <td>{{ number_format($item->amount, 0) }}</td>
                </tr>
                <tr class="desc">
                    <td colspan="4">{{ $item->variant->product->name }}{{ $item->variant->colour ? ' · '.mb_strtoupper($item->variant->colour->name) : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        {{-- Totals --}}
        <div class="dash" style="margin-top: 0;"></div>
        <div class="gap"></div>
        <div class="solid" style="margin-top: 0;"></div>
        <table class="totals">
            <tr><td>{{ __('Total before discount') }}</td><td>{{ number_format($sale->subtotal, 2) }}</td></tr>
            @if ((float) $sale->item_discount)<tr><td>{{ __('Item discounts') }}</td><td>-{{ number_format($sale->item_discount, 2) }}</td></tr>@endif
            @if ((float) $sale->bill_discount)<tr><td>{{ __('Bill discount') }}</td><td>-{{ number_format($sale->bill_discount, 2) }}</td></tr>@endif
            @if ((float) $sale->extra_charges)<tr><td>{{ __('Extra charges') }}</td><td>{{ number_format($sale->extra_charges, 2) }}</td></tr>@endif
        </table>
        <div class="gap"></div>
        <table class="totals">
            <tr class="net"><td>{{ __('Net total') }} ({{ $currency }})</td><td>{{ number_format($sale->total, 2) }}</td></tr>
            @foreach ($payments->reject(fn ($payment) => $payment->method->value === 'cash') as $payment)
                <tr><td>{{ mb_strtoupper($payment->method->label()) }}</td><td>{{ number_format($payment->amount, 2) }}</td></tr>
            @endforeach
            @if ($cashKept > 0)
                <tr><td>{{ __('Cash paid') }}</td><td>{{ number_format($cashGiven, 2) }}</td></tr>
                <tr class="bold"><td>{{ __('Change') }}</td><td>{{ number_format($cashGiven - $cashKept, 2) }}</td></tr>
            @endif
            @if ((float) $sale->credit_amount)
                <tr class="bold"><td>{{ __('On credit (udhaar)') }}</td><td>{{ number_format($sale->credit_amount, 2) }}</td></tr>
            @endif
            @if (\App\Models\Setting::isOn('receipt_show_item_count'))
                <tr><td>{{ __('Total Quantity (Pcs)') }}</td><td>{{ $pieces }}</td></tr>
            @endif
            @if (\App\Models\Setting::isOn('receipt_show_balance') && $sale->customer && (float) $sale->customer->balance > 0)
                <tr class="bold"><td>{{ __('Your total balance due') }}</td><td>{{ number_format($sale->customer->balance, 2) }}</td></tr>
            @endif
        </table>
        @if ($footer)
            <div style="margin-top: 2px;">{{ $footer }}</div>
        @endif
        @if (\App\Models\Setting::isOn('receipt_show_savings') && $saved > 0)
            <div class="note-box">{{ __('You saved :currency :amount', ['currency' => $currency, 'amount' => number_format($saved, 0)]) }}</div>
        @endif

        {{-- Exchange policy --}}
        @if ($policies)
            <div class="solid"></div>
            <div class="policy-title">{{ __('Exchange policy') }}</div>
            <ul class="policy">
                @foreach ($policies as $policy)
                    <li>{{ $policy }}</li>
                @endforeach
            </ul>
        @endif

        {{-- Invoice barcode, so a return can be found by scanning the bill. --}}
        <div class="solid"></div>
        <div class="center barcode" style="margin-top: 8px;">{!! \App\Support\Code128::svg($sale->number, 30) !!}</div>
        <div class="center">{{ $sale->number }}</div>
    </div>
</body>
</html>
