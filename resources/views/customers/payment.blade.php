<x-layouts.app :title="'Receive payment from '.$customer->name">
    <form method="POST" action="{{ route('customers.payments.store', $customer) }}" class="grid gap-6 lg:grid-cols-3" data-allocation-form>
        @csrf
        <div class="card card-body space-y-4 self-start">
            <div class="rounded-md bg-slate-50 px-4 py-3 text-sm">{{ __('Current balance due:') }} <span class="font-semibold">{{ number_format($customer->balance, 2) }}</span></div>
            <x-field name="payment_date" :label="__('Date')" type="date" :value="today()->toDateString()" required />
            <x-field name="amount" :label="__('Amount received')" type="number" step="0.01" min="0.01" :value="(float) $customer->balance > 0 ? (float) $customer->balance : null" required autofocus data-payment-amount />
            <x-select name="method" :label="__('Method')" :options="collect($methods)->mapWithKeys(fn ($m) => [$m->value => $m->label()])" selected="cash" required />
            <x-field name="reference_no" :label="__('Reference / transaction no.')" />
            <x-field name="notes" :label="__('Notes')" />
            <button class="btn btn-primary w-full py-3">{{ __('Save payment') }}</button>
            <a href="{{ route('customers.show', $customer) }}" class="btn btn-secondary w-full">{{ __('Cancel') }}</a>
        </div>

        <div class="card overflow-x-auto lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                <div>
                    <h2 class="font-semibold">{{ __('Apply to bills') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Filled oldest first as you type the amount. Change any line; whatever is left stays on account.') }}</p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-auto-allocate>{{ __('Oldest first') }}</button>
            </div>
            <table class="table">
                <thead><tr><th>{{ __('Bill') }}</th><th>{{ __('Date') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Still due') }}</th><th class="w-40 text-end">{{ __('Apply') }}</th></tr></thead>
                <tbody>
                @forelse ($documents as $index => $document)
                    <tr>
                        <td>
                            <input type="hidden" name="allocations[{{ $index }}][type]" value="{{ $document['type'] }}">
                            <input type="hidden" name="allocations[{{ $index }}][id]" value="{{ $document['id'] }}">
                            <span class="font-medium">{{ $document['number'] }}</span>
                            <span class="badge badge-gray">{{ $document['type'] === 'sale' ? __('Credit sale') : __('Stitching order') }}</span>
                        </td>
                        <td>{{ $document['date']->translatedFormat('d M Y') }}</td>
                        <td class="text-end">{{ number_format($document['total'], 2) }}</td>
                        <td class="text-end">{{ number_format($document['outstanding'], 2) }}</td>
                        <td><input type="number" step="0.01" min="0" max="{{ $document['outstanding'] }}" name="allocations[{{ $index }}][amount]" class="input text-end" data-allocation data-outstanding="{{ $document['outstanding'] }}"></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-500">{{ __('No open bills. The payment will be kept on account as an advance.') }}</td></tr>
                @endforelse
                </tbody>
                @if ($documents->isNotEmpty())
                    <tfoot>
                    <tr class="font-medium"><td colspan="4" class="text-end">{{ __('Applied') }}</td><td class="text-end" data-allocated>0.00</td></tr>
                    <tr class="text-slate-600"><td colspan="4" class="text-end">{{ __('Kept on account') }}</td><td class="text-end" data-unallocated>0.00</td></tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-allocation-form]');
            const amount = form.querySelector('[data-payment-amount]');
            const lines = [...form.querySelectorAll('[data-allocation]')];
            const fmt = (n) => n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const totals = () => {
                const applied = lines.reduce((sum, input) => sum + (parseFloat(input.value) || 0), 0);
                const left = (parseFloat(amount.value) || 0) - applied;
                form.querySelector('[data-allocated]')?.replaceChildren(fmt(applied));
                form.querySelector('[data-unallocated]')?.replaceChildren(fmt(left));
                form.querySelector('[data-unallocated]')?.classList.toggle('text-rose-600', left < -0.004);
            };

            const autoAllocate = () => {
                let remaining = parseFloat(amount.value) || 0;
                lines.forEach((input) => {
                    const take = Math.min(remaining, parseFloat(input.dataset.outstanding));
                    input.value = take > 0 ? take.toFixed(2) : '';
                    remaining = Math.round((remaining - Math.max(take, 0)) * 100) / 100;
                });
                totals();
            };

            amount.addEventListener('input', autoAllocate);
            lines.forEach((input) => input.addEventListener('input', totals));
            form.querySelector('[data-auto-allocate]')?.addEventListener('click', autoAllocate);
            autoAllocate();
        });
    </script>
</x-layouts.app>
