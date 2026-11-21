<x-print-layout :title="'Statement · '.$customer->name">
    <div class="mx-auto max-w-4xl p-8">
        <div class="flex items-start justify-between border-b border-slate-300 pb-4">
            <div>
                <x-bill-logo style="display: block; margin-bottom: 6px; max-height: 18mm; max-width: 60mm; object-fit: contain;" />
                <h1 class="text-xl font-bold">{{ $shopName }}</h1>
                <x-shop-contact class="text-sm text-slate-600" />
                <p class="text-sm text-slate-600">{{ __('Customer account statement') }}</p>
            </div>
            <div class="text-end text-sm">
                <div class="font-semibold">{{ $customer->name }}</div>
                <div>{{ $customer->phone }}</div>
                <div>{{ $customer->address }} {{ $customer->city }}</div>
            </div>
        </div>
        <p class="my-4 text-sm">
            {{ __('Period:') }}
            <strong>{{ isset($filters['from']) ? \Illuminate\Support\Carbon::parse($filters['from'])->translatedFormat('d M Y') : __('Start') }}</strong>
            {{ __('to') }}
            <strong>{{ isset($filters['to']) ? \Illuminate\Support\Carbon::parse($filters['to'])->translatedFormat('d M Y') : now()->translatedFormat('d M Y') }}</strong>
        </p>

        @include('suppliers._ledger-table', ['statement' => $statement, 'print' => true, 'debitLabel' => __('Charged'), 'creditLabel' => __('Paid / returned'), 'closingLabel' => __('Balance due')])

        <p class="mt-6 text-xs text-slate-500">{{ __('Printed') }} {{ now()->translatedFormat('d M Y H:i') }}{{ __('. A positive balance is the amount the customer owes; a negative balance is credit held for the customer.') }}</p>
    </div>
</x-print-layout>
