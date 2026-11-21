<x-layouts.app :title="$supplier->displayName()">
    <x-slot:actions>
        @can('purchases.manage')
            <a href="{{ route('purchases.create', ['supplier' => $supplier->id]) }}" class="btn btn-secondary">{{ __('New purchase') }}</a>
        @endcan
        @can('suppliers.pay')
            <a href="{{ route('suppliers.payments.create', $supplier) }}" class="btn btn-primary">{{ __('Record payment') }}</a>
        @endcan
        @can('suppliers.manage')
            <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-secondary">{{ __('Edit') }}</a>
        @endcan
        @can('records.delete')
            <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" data-confirm="{{ __('Delete this supplier? Their history is kept.') }}">
                @csrf @method('DELETE')
                <button class="btn btn-danger">{{ __('Delete') }}</button>
            </form>
        @endcan
    </x-slot:actions>

    <div class="grid gap-4 lg:grid-cols-4">
        <div class="card card-body lg:col-span-3">
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                <div><dt class="text-slate-500">{{ __('Phone') }}</dt><dd>{{ $supplier->phone ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('City') }}</dt><dd>{{ $supplier->city ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Address') }}</dt><dd>{{ $supplier->address ?? '—' }}</dd></div>
            </dl>
            @if ($supplier->notes)
                <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $supplier->notes }}</p>
            @endif
        </div>
        <div class="card card-body">
            <div class="text-sm text-slate-500">{{ __('Balance payable') }}</div>
            <div @class(['text-2xl font-semibold', 'text-rose-600' => (float) $supplier->balance > 0])>{{ number_format($supplier->balance, 2) }}</div>
        </div>
    </div>

    <div class="card mt-6 overflow-x-auto">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 px-5 py-3">
            <h2 class="font-semibold">{{ __('Ledger') }}</h2>
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <x-field name="from" :label="__('From')" type="date" :value="$filters['from'] ?? null" />
                <x-field name="to" :label="__('To')" type="date" :value="$filters['to'] ?? null" />
                <button class="btn btn-secondary">{{ __('Filter') }}</button>
                <a href="{{ route('suppliers.statement', [$supplier] + array_filter($filters)) }}" target="_blank" class="btn btn-secondary">{{ __('Print statement') }}</a>
            </form>
        </div>
        @include('suppliers._ledger-table', ['statement' => $statement])
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @can('purchases.view')
            <div class="card overflow-x-auto">
                <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold">{{ __('Recent purchases') }}</h2></div>
                <table class="table">
                    <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Invoice') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                    @forelse ($recentPurchases as $purchase)
                        <tr>
                            <td><a href="{{ route('purchases.show', $purchase) }}" class="text-indigo-600 hover:underline">{{ $purchase->number }}</a></td>
                            <td>{{ $purchase->purchase_date->translatedFormat('d M Y') }}</td>
                            <td>{{ $purchase->invoice_no }}</td>
                            <td class="text-end">{{ number_format($purchase->total, 2) }}</td>
                            <td><span @class(['badge', 'badge-green' => ! $purchase->isCancelled(), 'badge-red' => $purchase->isCancelled()])>{{ $purchase->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-center text-slate-500">{{ __('No purchases yet.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endcan

        <div class="card overflow-x-auto">
            <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold">{{ __('Recent payments') }}</h2></div>
            <table class="table">
                <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Amount') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($recentPayments as $payment)
                    <tr @class(['text-slate-400 line-through' => $payment->cancelled_at])>
                        <td>{{ $payment->number }}</td>
                        <td>{{ $payment->payment_date->translatedFormat('d M Y') }}</td>
                        <td>{{ $payment->method->label() }} {{ $payment->reference_no ? '· '.$payment->reference_no : '' }}</td>
                        <td class="text-end">{{ number_format($payment->amount, 2) }}</td>
                        <td class="text-end">
                            @if ($payment->cancelled_at)
                                <span class="badge badge-gray">{{ __('Cancelled') }}</span>
                            @elseif ($payment->source_id === null)
                                @can('suppliers.pay')
                                    <form method="POST" action="{{ route('suppliers.payments.cancel', [$supplier, $payment]) }}" class="flex gap-1" data-confirm="Cancel payment {{ $payment->number }}? The ledger entry will be reversed.">
                                        @csrf @method('PATCH')
                                        <input name="reason" placeholder="{{ __('Reason') }}" required class="input w-28 py-1 text-xs">
                                        <button class="btn btn-secondary btn-sm">{{ __('Cancel') }}</button>
                                    </form>
                                @endcan
                            @else
                                <span class="text-xs text-slate-500">{{ __('With purchase') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-center text-slate-500">{{ __('No payments yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
