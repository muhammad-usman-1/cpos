<x-print-layout :title="'Purchase voucher '.$purchase->number">
    <div class="mx-auto max-w-4xl p-8 text-sm">
        <div class="flex items-start justify-between border-b border-slate-300 pb-4">
            <div>
                <x-bill-logo style="display: block; margin-bottom: 6px; max-height: 18mm; max-width: 60mm; object-fit: contain;" />
                <h1 class="text-xl font-bold">{{ $shopName }}</h1>
                <x-shop-contact class="text-sm text-slate-600" />
                <p class="text-slate-600">{{ __('Purchase voucher') }}</p>
            </div>
            <div class="text-end">
                <div class="text-lg font-semibold">{{ $purchase->number }}</div>
                <div>{{ __('Date:') }} {{ $purchase->purchase_date->translatedFormat('d M Y') }}</div>
                @if ($purchase->invoice_no)<div>{{ __('Supplier invoice:') }} {{ $purchase->invoice_no }}</div>@endif
                @if ($purchase->isCancelled())<div class="font-bold text-rose-600">{{ __('CANCELLED') }}</div>@endif
            </div>
        </div>

        <div class="my-4">
            <div class="text-slate-500">{{ __('Supplier') }}</div>
            <div class="font-semibold">{{ $purchase->supplier->displayName() }}</div>
            <div>{{ $purchase->supplier->phone }} {{ $purchase->supplier->city }}</div>
        </div>

        <table class="table border border-slate-300">
            <thead><tr><th>#</th><th>{{ __('Product') }}</th><th>{{ __('SKU') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Rate') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
            <tbody>
            @foreach ($purchase->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->variant->displayName() }}</td>
                    <td class="font-mono text-xs">{{ $item->variant->sku }}</td>
                    <td class="text-end">{{ (float) $item->quantity }}</td>
                    <td class="text-end">{{ number_format($item->rate, 2) }}</td>
                    <td class="text-end">{{ number_format($item->amount, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="ms-auto mt-4 w-72 space-y-1">
            <div class="flex justify-between"><span>{{ __('Subtotal') }}</span><span>{{ number_format($purchase->subtotal, 2) }}</span></div>
            @if ((float) $purchase->discount)<div class="flex justify-between"><span>{{ __('Discount') }}</span><span>− {{ number_format($purchase->discount, 2) }}</span></div>@endif
            @if ((float) $purchase->other_charges)<div class="flex justify-between"><span>{{ __('Other charges') }}</span><span>{{ number_format($purchase->other_charges, 2) }}</span></div>@endif
            <div class="flex justify-between border-t border-slate-300 pt-1 font-semibold"><span>{{ __('Total (') }}{{ $currency }})</span><span>{{ number_format($purchase->total, 2) }}</span></div>
            <div class="flex justify-between"><span>{{ __('Paid') }}</span><span>{{ number_format($purchase->paid_amount, 2) }}</span></div>
            <div class="flex justify-between font-semibold"><span>{{ __('Balance') }}</span><span>{{ number_format($purchase->balanceDue(), 2) }}</span></div>
        </div>

        <div class="mt-16 flex justify-between text-xs text-slate-600">
            <div class="w-48 border-t border-slate-400 pt-1 text-center">{{ __('Prepared by:') }} {{ $purchase->creator?->name }}</div>
            <div class="w-48 border-t border-slate-400 pt-1 text-center">{{ __('Received by') }}</div>
        </div>
    </div>
</x-print-layout>
