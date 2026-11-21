{{-- Expects $statement from LedgerService::statement(). Labels default to the supplier view. --}}
@php
    $debitLabel ??= __('Debit (paid / returned)');
    $creditLabel ??= __('Credit (purchased)');
    $closingLabel ??= __('Closing balance payable');
    $print ??= false;
@endphp
<table class="table">
    <thead>
    <tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ $debitLabel }}</th><th class="text-end">{{ $creditLabel }}</th><th class="text-end">{{ __('Balance') }}</th></tr>
    </thead>
    <tbody>
    <tr class="bg-slate-50">
        <td colspan="5" class="font-medium">{{ __('Opening balance') }}</td>
        <td class="text-end font-medium">{{ number_format($statement['opening'], 2) }}</td>
    </tr>
    @forelse ($statement['entries'] as $entry)
        @php
            $reference = $entry->reference;
            $link = match (true) {
                $print => null,
                $reference instanceof \App\Models\Purchase => route('purchases.show', $reference),
                $reference instanceof \App\Models\Sale => route('sales.show', $reference),
                $reference instanceof \App\Models\SaleReturn => route('sale-returns.show', $reference),
                $reference instanceof \App\Models\StitchingOrder => route('orders.show', $reference),
                default => null,
            };
        @endphp
        <tr @class(['text-slate-400' => $entry->reversed_at])>
            <td class="whitespace-nowrap">{{ $entry->entry_date->translatedFormat('d M Y') }}</td>
            <td class="whitespace-nowrap">{{ $entry->typeLabel() }} @if ($entry->reversed_at)<span class="badge badge-gray no-print">{{ __('Reversed') }}</span>@endif</td>
            <td>
                @if ($link)
                    <a href="{{ $link }}" class="text-indigo-600 hover:underline">{{ $entry->description }}</a>
                @else
                    {{ $entry->description }}
                @endif
            </td>
            <td class="text-end">{{ (float) $entry->debit ? number_format($entry->debit, 2) : '' }}</td>
            <td class="text-end">{{ (float) $entry->credit ? number_format($entry->credit, 2) : '' }}</td>
            <td class="text-end font-medium">{{ number_format($entry->running_balance, 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="py-4 text-center text-slate-500">{{ __('No entries in this period.') }}</td></tr>
    @endforelse
    <tr class="bg-slate-50 font-semibold">
        <td colspan="3">{{ $closingLabel }}</td>
        <td class="text-end">{{ number_format($statement['debit'], 2) }}</td>
        <td class="text-end">{{ number_format($statement['credit'], 2) }}</td>
        <td class="text-end">{{ number_format($statement['closing'], 2) }}</td>
    </tr>
    </tbody>
</table>
