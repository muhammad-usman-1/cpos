<x-layouts.app :title="__('Receivables')">
    <form method="GET" class="card card-body mb-4 grid gap-3 sm:grid-cols-4">
        <x-field name="q" :label="__('Phone or name')" :value="request('q')" />
        <x-select name="bucket" :label="__('Has balance older than')" :options="$buckets" :selected="request('bucket')" :placeholder="__('Any age')" />
        <x-field name="min" :label="__('Balance at least')" type="number" min="0" :value="request('min')" />
        <div class="flex items-end"><button class="btn btn-secondary w-full">{{ __('Filter') }}</button></div>
    </form>

    <div class="mb-4 grid gap-4 sm:grid-cols-4">
        <div class="card card-body"><div class="text-sm text-slate-500">{{ __('Total receivable') }}</div><div class="text-2xl font-semibold">{{ number_format($total, 2) }}</div></div>
        @foreach ($buckets as $key => $label)
            <a href="{{ request()->fullUrlWithQuery(['bucket' => $key]) }}" @class(['card card-body hover:border-indigo-300', 'border-rose-300 bg-rose-50' => $key === '60_plus' && $totals[$key] > 0])>
                <div class="text-sm text-slate-500">{{ $label }}</div>
                <div @class(['text-2xl font-semibold', 'text-rose-600' => $key === '60_plus' && $totals[$key] > 0])>{{ number_format($totals[$key], 2) }}</div>
            </a>
        @endforeach
    </div>

    <form method="POST" action="{{ route('receivables.remind') }}" @messaging data-confirm="{{ __('Send a payment reminder to the ticked customers?') }}" @endmessaging>
        @csrf
        <div class="card overflow-x-auto">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                <p class="text-xs text-slate-500">{{ __('Ageing assumes payments clear the oldest charges first.') }}</p>
                @messaging
                    @can('messages.send')
                        <button class="btn btn-primary btn-sm">{{ __('Send payment reminder to ticked') }}</button>
                    @endcan
                @endmessaging
            </div>
            <table class="table">
                <thead>
                <tr>
                    @messaging<th class="w-8"><input type="checkbox" onclick="document.querySelectorAll('[data-remind]').forEach((box) => box.checked = this.checked)"></th>@endmessaging
                    <th>{{ __('Customer') }}</th><th>{{ __('Phone') }}</th>
                    @foreach ($buckets as $label)<th class="text-end">{{ $label }}</th>@endforeach
                    <th class="text-end">{{ __('Total due') }}</th><th>{{ __('Oldest unpaid') }}</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @messaging<td><input type="checkbox" name="customers[]" value="{{ $row['customer']->id }}" data-remind></td>@endmessaging
                        <td><a href="{{ route('customers.show', $row['customer']) }}" class="font-medium text-indigo-600 hover:underline">{{ $row['customer']->name }}</a></td>
                        <td class="font-mono text-xs">{{ $row['customer']->phone }}</td>
                        @foreach (array_keys($buckets) as $key)
                            <td @class(['text-end', 'text-slate-300' => $row['buckets'][$key] == 0, 'font-semibold text-rose-600' => $key === '60_plus' && $row['buckets'][$key] > 0])>{{ number_format($row['buckets'][$key], 2) }}</td>
                        @endforeach
                        <td class="text-end font-semibold">{{ number_format($row['balance'], 2) }}</td>
                        <td class="whitespace-nowrap text-xs">{{ $row['oldest']?->translatedFormat('d M Y') }}</td>
                        <td class="whitespace-nowrap text-end">
                            @can('payments.receive')
                                <a href="{{ route('customers.payments.create', $row['customer']) }}" class="btn btn-secondary btn-sm">{{ __('Receive') }}</a>
                            @endcan
                            <a href="{{ route('customers.statement', $row['customer']) }}" target="_blank" class="btn btn-secondary btn-sm">{{ __('Statement') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-6 text-center text-slate-500">{{ __('No customer owes anything.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </form>
</x-layouts.app>
