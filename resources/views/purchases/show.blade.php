<x-layouts.app :title="'Purchase '.$purchase->number">
    <x-slot:actions>
        <a href="{{ route('purchases.voucher', $purchase) }}" target="_blank" class="btn btn-secondary">{{ __('Print voucher') }}</a>
        @can('products.manage')
            @barcodes<a href="{{ route('labels.create', ['purchase' => $purchase->id]) }}" class="btn btn-secondary">{{ __('Print labels') }}</a>@endbarcodes
        @endcan
        @if (! $purchase->isCancelled())
            @can('purchases.return')
                <a href="{{ route('purchase-returns.create', $purchase) }}" class="btn btn-secondary">{{ __('Return items') }}</a>
            @endcan
            @if ($purchase->returns->isEmpty())
                @can('purchases.manage')
                    <a href="{{ route('purchases.edit', $purchase) }}" class="btn btn-primary">{{ __('Edit') }}</a>
                @endcan
            @endif
        @endif
    </x-slot:actions>

    @if ($purchase->isCancelled())
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ __('Cancelled on') }} {{ $purchase->cancelled_at->translatedFormat('d M Y H:i') }} {{ __('by') }} {{ $purchase->canceller?->name }}{{ __('. Reason:') }} {{ $purchase->cancel_reason }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card card-body lg:col-span-2">
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                <div><dt class="text-slate-500">{{ __('Supplier') }}</dt><dd>@can('suppliers.view')<a href="{{ route('suppliers.show', $purchase->supplier) }}" class="text-indigo-600 hover:underline">{{ $purchase->supplier->displayName() }}</a>@else{{ $purchase->supplier->displayName() }}@endcan</dd></div>
                <div><dt class="text-slate-500">{{ __('Date') }}</dt><dd>{{ $purchase->purchase_date->translatedFormat('d M Y') }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Supplier invoice') }}</dt><dd>{{ $purchase->invoice_no ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Entered by') }}</dt><dd>{{ $purchase->creator?->name }} · {{ $purchase->created_at->translatedFormat('d M Y H:i') }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Status') }}</dt><dd><span @class(['badge', 'badge-green' => ! $purchase->isCancelled(), 'badge-red' => $purchase->isCancelled()])>{{ $purchase->status->label() }}</span></dd></div>
                <div><dt class="text-slate-500">{{ __('Invoice attachment') }}</dt><dd>@if ($purchase->attachmentUrl())<a href="{{ $purchase->attachmentUrl() }}" target="_blank" class="text-indigo-600 hover:underline">{{ __('View') }}</a>@else — @endif</dd></div>
            </dl>
            @if ($purchase->notes)
                <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $purchase->notes }}</p>
            @endif
        </div>
        <div class="card card-body space-y-2 text-sm">
            <div class="flex justify-between"><span>{{ __('Subtotal') }}</span><span>{{ number_format($purchase->subtotal, 2) }}</span></div>
            <div class="flex justify-between"><span>{{ __('Discount') }}</span><span>− {{ number_format($purchase->discount, 2) }}</span></div>
            <div class="flex justify-between"><span>{{ __('Other charges') }}</span><span>{{ number_format($purchase->other_charges, 2) }}</span></div>
            <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><span>{{ __('Total') }}</span><span>{{ number_format($purchase->total, 2) }}</span></div>
            <div class="flex justify-between"><span>{{ __('Paid') }}{{ $purchase->payment_method ? ' ('.$purchase->payment_method->label().')' : '' }}</span><span>{{ number_format($purchase->paid_amount, 2) }}</span></div>
            <div class="flex justify-between font-medium"><span>{{ __('Balance to supplier') }}</span><span>{{ number_format($purchase->balanceDue(), 2) }}</span></div>
        </div>
    </div>

    <div class="card mt-6 overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Product') }}</th><th>{{ __('SKU') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Returned') }}</th><th class="text-end">{{ __('Rate') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
            <tbody>
            @foreach ($purchase->items as $item)
                <tr>
                    <td><a href="{{ route('products.show', $item->variant->product) }}" class="text-indigo-600 hover:underline">{{ $item->variant->displayName() }}</a></td>
                    <td class="font-mono text-xs">{{ $item->variant->sku }}</td>
                    <td class="text-end">{{ (float) $item->quantity }}</td>
                    <td class="text-end">{{ (float) $item->returned_qty ?: '' }}</td>
                    <td class="text-end">{{ number_format($item->rate, 2) }}</td>
                    <td class="text-end">{{ number_format($item->amount, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if ($purchase->returns->isNotEmpty())
        <div class="card mt-6 overflow-x-auto">
            <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold">{{ __('Returns') }}</h2></div>
            <table class="table">
                <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('By') }}</th></tr></thead>
                <tbody>
                @foreach ($purchase->returns as $return)
                    <tr>
                        <td><a href="{{ route('purchase-returns.show', $return) }}" class="text-indigo-600 hover:underline">{{ $return->number }}</a></td>
                        <td>{{ $return->return_date->translatedFormat('d M Y') }}</td>
                        <td class="text-end">{{ number_format($return->total, 2) }}</td>
                        <td>{{ $return->creator?->name }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if (! $purchase->isCancelled() && $purchase->returns->isEmpty())
        @can('purchases.cancel')
            <form method="POST" action="{{ route('purchases.cancel', $purchase) }}" class="card card-body mt-6 flex flex-wrap items-end gap-3" data-confirm="Cancel purchase {{ $purchase->number }}? Stock and supplier balance will be reversed.">
                @csrf @method('PATCH')
                <x-field name="reason" :label="__('Cancel this purchase. Reason')" required class="flex-1" />
                <button class="btn btn-danger">{{ __('Cancel purchase') }}</button>
            </form>
        @endcan
    @endif
</x-layouts.app>
