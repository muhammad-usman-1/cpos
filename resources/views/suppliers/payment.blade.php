<x-layouts.app :title="'Pay '.$supplier->displayName()">
    <form method="POST" action="{{ route('suppliers.payments.store', $supplier) }}" class="card card-body space-y-4">
        @csrf
        <div class="rounded-md bg-slate-50 px-4 py-3 text-sm">{{ __('Current balance payable:') }} <span class="font-semibold">{{ number_format($supplier->balance, 2) }}</span></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="payment_date" :label="__('Date')" type="date" :value="today()->toDateString()" required />
            <x-field name="amount" :label="__('Amount')" type="number" step="0.01" min="0.01" :value="(float) $supplier->balance > 0 ? (float) $supplier->balance : null" required />
            <x-select name="method" :label="__('Method')" :options="collect($methods)->mapWithKeys(fn ($m) => [$m->value => $m->label()])" selected="cash" required />
            <x-field name="reference_no" :label="__('Cheque / transaction no.')" :hint="__('Required for cheque and bank transfer.')" />
        </div>
        <x-field name="notes" :label="__('Notes')" />
        <div class="flex gap-2">
            <button class="btn btn-primary">{{ __('Save payment') }}</button>
            <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
