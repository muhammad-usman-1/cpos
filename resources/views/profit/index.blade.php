<x-layouts.app :title="__('Profit summary')">
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
        <x-field name="from" :label="__('From')" type="date" :value="$from" />
        <x-field name="to" :label="__('To')" type="date" :value="$to" />
        <button class="btn btn-secondary">{{ __('Show') }}</button>
        <button type="button" onclick="window.print()" class="btn btn-secondary">{{ __('Print') }}</button>
    </form>

    <div class="grid gap-6">
        <div class="card card-body space-y-2 text-sm">
            <h2 class="mb-2 font-semibold">{{ __('Trading') }}</h2>
            <div class="flex justify-between"><span>{{ __('Sales (after discounts)') }}</span><span>{{ number_format($summary['sales'], 2) }}</span></div>
            <div class="flex justify-between text-slate-600"><span>{{ __('Less returns') }}</span><span>− {{ number_format($summary['returns'], 2) }}</span></div>
            <div class="flex justify-between border-t border-slate-200 pt-2 font-medium"><span>{{ __('Net sales') }}</span><span>{{ number_format($summary['net_sales'], 2) }}</span></div>
            <div class="flex justify-between text-slate-600"><span>{{ __('Cost of goods sold (average cost)') }}</span><span>− {{ number_format($summary['cost_of_sales'], 2) }}</span></div>
            <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><span>{{ __('Gross profit (') }}{{ $summary['gross_margin'] }}%)</span><span>{{ number_format($summary['gross_profit'], 2) }}</span></div>
            <div class="flex justify-between"><span>{{ __('Stitching income (orders delivered)') }}</span><span>{{ number_format($summary['stitching_income'], 2) }}</span></div>
            <div class="flex justify-between text-slate-600"><span>{{ __('Less tailor piece payments') }}</span><span>− {{ number_format($summary['tailor_cost'], 2) }}</span></div>
        </div>

    </div>

    <div @class(['card card-body mt-6 flex items-center justify-between text-lg font-semibold', 'border-emerald-300 bg-emerald-50 text-emerald-800' => $summary['net_profit'] >= 0, 'border-rose-300 bg-rose-50 text-rose-800' => $summary['net_profit'] < 0])>
        <span>{{ $summary['net_profit'] >= 0 ? __('Net profit') : __('Net loss') }}</span>
        <span>{{ number_format($summary['net_profit'], 2) }}</span>
    </div>
    <p class="mt-2 text-xs text-slate-500">{{ __('Net profit = gross profit + stitching income − tailor piece payments. Sales on credit count when sold, not when paid.') }}</p>
</x-layouts.app>
