@php
    $balance = (float) $customer->balance;
    $lastVisit = $stats['last_visit'] ? \Illuminate\Support\Carbon::parse($stats['last_visit']) : null;
@endphp

<x-layouts.app :title="$customer->name" :heading="false">
    {{-- Header: who the customer is, what to do next, and the key figures. --}}
    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-5 bg-indigo-600 p-5 text-white md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-4">
                @if ($customer->photoUrl())
                    <a href="{{ $customer->photoUrl() }}" target="_blank" class="shrink-0" title="{{ __('Open photo') }}">
                        <img src="{{ $customer->photoUrl() }}" alt="{{ $customer->name }}" class="h-20 w-20 rounded-full object-cover ring-4 ring-white/30">
                    </a>
                @else
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-white/15 text-3xl font-bold ring-4 ring-white/20">
                        {{ mb_strtoupper(mb_substr($customer->name, 0, 1)) }}
                    </div>
                @endif
                <div class="min-w-0">
                    <h1 class="flex flex-wrap items-center gap-2 text-2xl font-bold">
                        {{ $customer->name }}
                        @unless ($customer->is_active)<span class="rounded-full bg-white/20 px-2 py-0.5 text-xs font-medium">{{ __('Inactive') }}</span>@endunless
                    </h1>
                    <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-indigo-100">
                        <span class="inline-flex items-center gap-1 font-mono text-base text-white" dir="ltr">{{ $customer->phone }}</span>
                        @if ($customer->city)<span>{{ $customer->city }}</span>@endif
                        @if ((float) $customer->discount_percent)<span class="rounded-full bg-white/15 px-2 py-0.5">{{ __('Usual discount') }} {{ (float) $customer->discount_percent }}%</span>@endif
                        <span>{{ __('Customer since') }} {{ $customer->created_at->translatedFormat('M Y') }}</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @can('sales.create')
                    <a href="{{ route('sales.create', ['customer' => $customer->id]) }}" class="btn bg-white text-indigo-700 hover:bg-indigo-50"><x-icon name="cart" class="me-1.5 h-4 w-4" />{{ __('New sale') }}</a>
                @endcan
                @can('orders.manage')
                    <a href="{{ route('orders.create', ['customer' => $customer->id]) }}" class="btn bg-white text-indigo-700 hover:bg-indigo-50"><x-icon name="scissors" class="me-1.5 h-4 w-4" />{{ __('New stitching order') }}</a>
                @endcan
                @can('payments.receive')
                    <a href="{{ route('customers.payments.create', $customer) }}" class="btn bg-white/15 text-white hover:bg-white/25">{{ __('Receive payment') }}</a>
                @endcan
                @can('receivables.view')
                    <a href="{{ route('customers.statement', [$customer] + array_filter($filters)) }}" target="_blank" class="btn bg-white/15 text-white hover:bg-white/25">{{ __('Statement') }}</a>
                @endcan
                @can('customers.manage')
                    <a href="{{ route('customers.edit', $customer) }}" class="btn bg-white/15 text-white hover:bg-white/25">{{ __('Edit') }}</a>
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-2 divide-slate-200 md:grid-cols-4 md:divide-x rtl:md:divide-x-reverse">
            <div class="p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $balance < 0 ? __('Credit with shop') : __('Balance due') }}</div>
                <div @class(['mt-1 text-2xl font-bold tabular-nums', 'text-rose-600' => $balance > 0, 'text-emerald-700' => $balance < 0, 'text-slate-900' => $balance == 0])>{{ number_format(abs($balance), 2) }}</div>
            </div>
            <div class="p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Total spent') }}</div>
                <div class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ number_format($stats['spent'], 0) }}</div>
            </div>
            <div class="p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Bills') }}</div>
                <div class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $stats['bills'] }}</div>
                <div class="text-xs text-slate-500">{{ $lastVisit ? __('Last visit :date', ['date' => $lastVisit->translatedFormat('d M Y')]) : __('No visits yet') }}</div>
            </div>
            <div class="p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Open orders') }}</div>
                <div @class(['mt-1 text-2xl font-bold tabular-nums', 'text-amber-600' => $stats['open_orders'] > 0, 'text-slate-900' => ! $stats['open_orders']])>{{ $stats['open_orders'] }}</div>
                <div class="text-xs text-slate-500">{{ __(':count orders in all', ['count' => $stats['orders']]) }}</div>
            </div>
        </div>

        @if ($customer->notes)
            <div class="border-t border-slate-200 bg-amber-50 px-5 py-2.5 text-sm text-amber-900 whitespace-pre-line">{{ $customer->notes }}</div>
        @endif
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        {{-- Measurements for the customer and each family member. --}}
        <section class="xl:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold">{{ __('Family and measurements') }}</h2>
                <span class="text-sm text-slate-500">{{ trans_choice(':count person|:count people', $customer->profiles->count(), ['count' => $customer->profiles->count()]) }}</span>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($customer->profiles as $profile)
                    @php
                        $current = $profile->currentMeasurement;
                        $values = $current?->valueMap() ?? [];
                        $profileFields = $fields->get($profile->template->value, collect());
                        $isSelf = $profile->relation === \App\Enums\FamilyRelation::Self;
                    @endphp
                    <div class="flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                        <div class="flex items-start justify-between gap-2 border-b border-slate-100 px-4 py-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <div @class(['flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold', 'bg-indigo-600 text-white' => $isSelf, 'bg-indigo-50 text-indigo-700' => ! $isSelf])>
                                    {{ mb_strtoupper(mb_substr($isSelf ? $customer->name : $profile->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate font-semibold">{{ $isSelf ? $customer->name : $profile->name }}</div>
                                    <div class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                        <span>{{ $profile->relation?->label() ?? __('Self') }}</span>
                                        <span class="badge badge-gray">{{ $profile->template->label() }}</span>
                                        @if ($profile->phone)<span class="font-mono" dir="ltr">{{ $profile->phone }}</span>@endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                @can('orders.manage')
                                    <a href="{{ route('orders.create', ['customer' => $customer->id, 'profile' => $profile->id]) }}" class="btn btn-secondary btn-sm">{{ __('Order') }}</a>
                                @endcan
                                @can('measurements.manage')
                                    <a href="{{ route('measurements.create', $profile) }}" class="btn btn-primary btn-sm">{{ $current ? __('Edit') : __('Take measurements') }}</a>
                                @endcan
                            </div>
                        </div>

                        <div class="flex-1 px-4 py-3 text-sm">
                            @if ($current)
                                <dl class="grid grid-cols-2 gap-x-4 gap-y-1">
                                    @foreach ($profileFields as $field)
                                        @if (isset($values[$field->id]))
                                            <div class="flex justify-between border-b border-dashed border-slate-100 py-0.5"><dt class="text-slate-500">{{ __($field->label) }}</dt><dd class="font-semibold">{{ __($values[$field->id]) }}</dd></div>
                                        @endif
                                    @endforeach
                                </dl>
                                @if ($current->notes)
                                    <p class="mt-2 rounded bg-slate-50 px-2 py-1 text-xs text-slate-600">{{ $current->notes }}</p>
                                @endif
                                <div class="mt-2 flex flex-wrap gap-x-3 text-xs text-slate-500">
                                    <span>{{ __('Version') }} {{ $current->version }} · {{ $current->created_at->translatedFormat('d M Y') }} · {{ $current->unitLabel() }}</span>
                                    <a href="{{ route('measurements.show', $current) }}" class="text-indigo-600 hover:underline">{{ __('History') }} ({{ $profile->measurements_count }})</a>
                                    <a href="{{ route('measurements.print', $current) }}" target="_blank" class="text-indigo-600 hover:underline">{{ __('Print') }}</a>
                                </div>
                            @else
                                <div class="flex h-full flex-col items-center justify-center py-4 text-center text-slate-500">
                                    <x-icon name="ruler" class="mb-1 h-6 w-6 text-slate-300" />
                                    {{ __('No measurements yet.') }}
                                </div>
                            @endif
                        </div>

                        @if ($customer->profiles->count() > 1 && ! $isSelf)
                            @can('customers.manage')
                                <form method="POST" action="{{ route('customers.profiles.destroy', [$customer, $profile]) }}" class="border-t border-slate-100 bg-slate-50 px-4 py-2 text-end text-xs" data-confirm="{{ __('Remove :name?', ['name' => $profile->label()]) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-600 hover:underline">{{ __('Remove') }}</button>
                                </form>
                            @endcan
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Side column: add a family member, recent payments. --}}
        <aside class="space-y-6">
            @can('customers.manage')
                <form method="POST" action="{{ route('customers.profiles.store', $customer) }}" class="space-y-2.5 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    @csrf
                    <h3 class="flex items-center gap-1.5 font-semibold text-indigo-700"><x-icon name="user-plus" class="h-5 w-5" /> {{ __('Add family member') }}</h3>
                    <input name="name" placeholder="{{ __('Name, e.g. Rashid') }}" class="input" required>
                    <div class="grid grid-cols-2 gap-2">
                        <select name="relation" class="input" aria-label="{{ __('Relation') }}">
                            @foreach ($relations as $relation)
                                <option value="{{ $relation->value }}">{{ $relation->label() }}</option>
                            @endforeach
                        </select>
                        <select name="template" class="input" aria-label="{{ __('Measurement form') }}">
                            @foreach ($templates as $template)
                                <option value="{{ $template->value }}">{{ $template->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input name="phone" placeholder="{{ __('Phone (optional)') }}" class="input" inputmode="tel" autocomplete="off" data-phone-check>
                    <button class="btn btn-primary w-full">{{ __('Add and take measurements') }}</button>
                </form>
            @endcan

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h3 class="font-semibold">{{ __('Payments') }}</h3>
                    @can('payments.receive')
                        <a href="{{ route('customers.payments.create', $customer) }}" class="text-sm text-indigo-600 hover:underline">{{ __('+ Receive') }}</a>
                    @endcan
                </div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($payments as $payment)
                        <li @class(['px-4 py-2.5', 'text-slate-400 line-through' => $payment->cancelled_at])>
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium">{{ $payment->payment_date->translatedFormat('d M Y') }}</span>
                                <span @class(['font-semibold tabular-nums', 'text-rose-600' => $payment->direction === 'out', 'text-emerald-700' => $payment->direction === 'in' && ! $payment->cancelled_at])>
                                    {{ $payment->direction === 'out' ? '−' : '+' }}{{ number_format($payment->amount, 2) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-2 text-xs text-slate-500">
                                <span>{{ $payment->method->label() }} · {{ $payment->source?->number ?? __('On account') }} · {{ $payment->number }}</span>
                                @if (! $payment->cancelled_at && $payment->source_id === null && $payment->direction === 'in')
                                    @can('payments.receive')
                                        <details class="text-end">
                                            <summary class="cursor-pointer text-slate-400">{{ __('cancel') }}</summary>
                                            <form method="POST" action="{{ route('customers.payments.cancel', [$customer, $payment]) }}" class="mt-1 flex gap-1" data-confirm="Cancel payment {{ $payment->number }}?">
                                                @csrf @method('PATCH')
                                                <input name="reason" placeholder="{{ __('Reason') }}" required class="input w-24 py-1 text-xs">
                                                <button class="btn btn-secondary btn-sm">{{ __('OK') }}</button>
                                            </form>
                                        </details>
                                    @endcan
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center text-slate-500">{{ __('No payments yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </aside>
    </div>

    {{-- Orders and bills. --}}
    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        @can('orders.view')
            <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="font-semibold">{{ __('Stitching orders') }}</h2>
                    @can('orders.manage')
                        <a href="{{ route('orders.create', ['customer' => $customer->id]) }}" class="text-sm text-indigo-600 hover:underline">{{ __('+ New order') }}</a>
                    @endcan
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>{{ __('Order') }}</th><th>{{ __('For') }}</th><th>{{ __('Promised') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
                        <tbody>
                        @forelse ($orders as $order)
                            <tr data-href="{{ route('orders.show', $order) }}">
                                <td class="whitespace-nowrap"><a href="{{ route('orders.show', $order) }}" class="font-medium text-indigo-600 hover:underline">{{ $order->number }}</a> @if ($order->type->value === 'alteration')<span class="badge badge-gray">{{ __('Alteration') }}</span>@endif</td>
                                <td>{{ $order->profile->label() }}</td>
                                <td @class(['whitespace-nowrap', 'font-medium text-rose-600' => $order->isOverdue()])>{{ $order->promised_date->translatedFormat('d M Y') }}</td>
                                <td><span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                                <td class="text-end tabular-nums">{{ number_format($order->balanceDue(), 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-slate-500">{{ __('No orders yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endcan

        @can('sales.view')
            <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="font-semibold">{{ __('Purchase history') }}</h2>
                    @can('sales.create')
                        <a href="{{ route('sales.create', ['customer' => $customer->id]) }}" class="text-sm text-indigo-600 hover:underline">{{ __('+ New sale') }}</a>
                    @endcan
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Items') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                        <tbody>
                        @forelse ($sales as $sale)
                            <tr data-href="{{ route('sales.show', $sale) }}">
                                <td class="whitespace-nowrap"><a href="{{ route('sales.show', $sale) }}" class="font-medium text-indigo-600 hover:underline">{{ $sale->number }}</a></td>
                                <td class="whitespace-nowrap">{{ $sale->sale_date->translatedFormat('d M Y') }}</td>
                                <td class="max-w-56 truncate text-xs" title="{{ $sale->items->map(fn ($item) => $item->variant->displayName().' × '.(float) $item->quantity)->implode(', ') }}">{{ $sale->items->map(fn ($item) => $item->variant->displayName().' × '.(float) $item->quantity)->implode(', ') }}</td>
                                <td class="text-end tabular-nums">
                                    {{ number_format($sale->total, 0) }}
                                    @if ((float) $sale->credit_amount)<div class="text-xs text-rose-600">{{ __('credit') }} {{ number_format($sale->credit_amount, 0) }}</div>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-slate-500">{{ __('No purchases yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endcan
    </div>

    {{-- Account ledger. --}}
    <section class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-100 px-4 py-3">
            <h2 class="font-semibold">{{ __('Account ledger') }}</h2>
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <x-field name="from" :label="__('From')" type="date" :value="$filters['from'] ?? null" />
                <x-field name="to" :label="__('To')" type="date" :value="$filters['to'] ?? null" />
                <button class="btn btn-secondary">{{ __('Filter') }}</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            @include('suppliers._ledger-table', ['statement' => $statement, 'debitLabel' => __('Debit (bought / charged)'), 'creditLabel' => __('Credit (paid / returned)'), 'closingLabel' => __('Closing balance due')])
        </div>
    </section>
</x-layouts.app>
