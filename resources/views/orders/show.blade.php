@php
    $open = ! $order->status->isClosed();
    $methodOptions = collect($methods)->reject(fn ($m) => $m->value === 'cheque')->mapWithKeys(fn ($m) => [$m->value => $m->label()]);
@endphp

<x-layouts.app :title="'Order '.$order->number">
    <x-slot:actions>
        <a href="{{ route('orders.slip', $order) }}" target="_blank" class="btn btn-secondary">{{ __('Customer slip') }}</a>
        <a href="{{ route('orders.suit-tag', $order) }}" target="_blank" class="btn btn-secondary">{{ __('Suit tag') }}</a>
        <a href="{{ route('orders.measurement-slip', $order) }}" target="_blank" class="btn btn-secondary">{{ __('Tailor slip') }}</a>
        @if ($readyMessage && \App\Models\Setting::usesMessaging())
            <a href="{{ $readyMessage }}" target="_blank" rel="noopener" class="btn btn-primary">{{ __('Send "ready" on WhatsApp') }}</a>
        @endif
        @can('orders.manage')
            @if ($open)
                <a href="{{ route('orders.edit', $order) }}" class="btn btn-secondary">{{ __('Edit') }}</a>
            @elseif ($order->status->value === 'delivered')
                <a href="{{ route('orders.create', ['customer' => $order->customer_id, 'parent' => $order->id, 'profile' => $order->customer_profile_id]) }}" class="btn btn-secondary">{{ __('Alteration') }}</a>
            @endif
        @endcan
    </x-slot:actions>

    @if (session('print_slip'))
        <script>window.addEventListener('load', () => window.open(@json(route('orders.slip', $order)), '_blank'));</script>
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
            <span>{{ __('Print both slips: one for the customer and one to tie to the suit.') }}</span>
            <a href="{{ route('orders.slip', $order) }}" target="_blank" class="btn btn-sm btn-secondary">{{ __('Customer slip') }}</a>
            <a href="{{ route('orders.suit-tag', $order) }}" target="_blank" class="btn btn-sm btn-primary">{{ __('Suit tag') }}</a>
        </div>
    @endif
    @if (session('notify_ready') && $readyMessage && \App\Models\Setting::usesMessaging())
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ __('Order is ready.') }} <a href="{{ $readyMessage }}" target="_blank" rel="noopener" class="font-medium underline">{{ __('Tell') }} {{ $order->customer->name }} {{ __('on WhatsApp') }}</a>.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card card-body">
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <span class="badge {{ $order->status->badgeClass() }} px-3 py-1 text-sm">{{ $order->status->label() }}</span>
                    @if ($order->isOverdue())<span class="badge badge-red px-3 py-1 text-sm">{{ __('Overdue') }}</span>@endif
                    <span class="badge badge-gray">{{ $order->type->label() }}</span>
                    @if ($order->parent)
                        <span class="text-sm">{{ __('of') }} <a href="{{ route('orders.show', $order->parent) }}" class="text-indigo-600 hover:underline">{{ $order->parent->number }}</a></span>
                    @endif
                </div>
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-slate-500">{{ __('Customer') }}</dt><dd>
                        @can('customers.view')
                            <a href="{{ route('customers.show', $order->customer) }}" class="text-indigo-600 hover:underline">{{ $order->customer->name }}</a>
                        @else
                            {{ $order->customer->name }}
                        @endcan
                        · {{ $order->customer->phone }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('For') }}</dt><dd>{{ $order->profile->label() }} ({{ $order->profile->template->label() }})</dd></div>
                    <div><dt class="text-slate-500">{{ __('Pieces') }}</dt><dd>{{ $order->pieces }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('Taken') }}</dt><dd>{{ $order->order_date->translatedFormat('d M Y') }} · {{ $order->creator?->name }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('Delivery date') }}</dt><dd @class(['font-semibold', 'text-rose-600' => $order->isOverdue()])>{{ $order->promised_date->translatedFormat('D d M Y') }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('Tailor') }}</dt><dd>{{ $order->tailor?->name ?? __('Not assigned') }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('Cloth') }}</dt><dd>
                        @if ($order->cloth_source->value === 'shop')
                            {{ __('Bought from shop') }} @if ($order->sale) (<a href="{{ route('sales.show', $order->sale) }}" class="text-indigo-600 hover:underline">{{ $order->sale->number }}</a>)@endif
                        @else
                            {{ $order->cloth_description }}{{ $order->cloth_quantity ? ' · '.(float) $order->cloth_quantity.' m' : '' }}
                        @endif
                    </dd></div>
                    @if ($order->ready_at)<div><dt class="text-slate-500">{{ __('Ready') }}</dt><dd>{{ $order->ready_at->translatedFormat('d M Y H:i') }}</dd></div>@endif
                    @if ($order->delivered_at)<div><dt class="text-slate-500">{{ __('Delivered') }}</dt><dd>{{ $order->delivered_at->translatedFormat('d M Y H:i') }}</dd></div>@endif
                </dl>
                @if ($order->design_notes)
                    <p class="mt-4 whitespace-pre-line rounded-md bg-slate-50 p-3 text-sm"><span class="font-medium">{{ __('Design:') }}</span> {{ $order->design_notes }}</p>
                @endif
                @if ($order->cancel_reason)
                    <p class="mt-4 rounded-md bg-rose-50 p-3 text-sm text-rose-800">{{ __('Cancelled:') }} {{ $order->cancel_reason }}</p>
                @endif
            </div>

            <div class="card card-body">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold">{{ __('Measurements used') }}</h2>
                    @if ($order->measurement)
                        <a href="{{ route('measurements.show', $order->measurement) }}" class="text-sm text-indigo-600 hover:underline">{{ __('Version') }} {{ $order->measurement->version }} · {{ $order->measurement->unitLabel() }}</a>
                    @endif
                </div>
                @if ($order->measurement)
                    <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-3">
                        @foreach ($order->measurement->values->sortBy(fn ($v) => [$v->field?->group, $v->field?->sort_order]) as $value)
                            <div class="flex justify-between border-b border-dashed border-slate-100"><dt class="text-slate-500">{{ __($value->field?->label) }}</dt><dd class="font-medium">{{ __($value->value) }}</dd></div>
                        @endforeach
                    </dl>
                    @if ($order->measurement->notes)<p class="mt-2 text-sm">{{ $order->measurement->notes }}</p>@endif
                @else
                    <p class="text-sm text-slate-500">{{ __('No measurement attached.') }}</p>
                @endif
            </div>

            <div class="card overflow-x-auto">
                <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold">{{ __('Slips printed') }} ({{ $order->slipPrints->count() }})</h2></div>
                @if ($order->slipPrints->isEmpty())
                    <p class="px-5 py-4 text-sm text-slate-500">{{ __('No slip printed yet.') }}</p>
                @else
                    <table class="table">
                        <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Slip') }}</th><th>{{ __('Copy') }}</th><th>{{ __('By') }}</th></tr></thead>
                        <tbody>
                        @foreach ($order->slipPrints as $print)
                            <tr>
                                <td class="whitespace-nowrap text-xs">{{ $print->created_at->translatedFormat('d M Y h:i A') }}</td>
                                <td>{{ ['customer' => __('Customer slip'), 'suit_tag' => __('Suit tag'), 'tailor' => __('Tailor slip')][$print->new_values['slip'] ?? ''] ?? '—' }}</td>
                                <td>{{ ($print->new_values['copy'] ?? 1) > 1 ? __('Duplicate copy :number', ['number' => $print->new_values['copy']]) : __('First print') }}</td>
                                <td class="text-xs text-slate-500">{{ $print->user?->name }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="card overflow-x-auto">
                <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold">{{ __('History') }}</h2></div>
                <table class="table">
                    <tbody>
                    @foreach ($order->statusLogs as $log)
                        <tr>
                            <td class="whitespace-nowrap text-xs">{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td>{{ $log->from_status && $log->from_status !== $log->to_status ? $log->from_status->label().' → ' : '' }}<span class="font-medium">{{ $log->to_status->label() }}</span></td>
                            <td class="text-xs">{{ __($log->notes) }}</td>
                            <td class="text-xs text-slate-500">{{ $log->user?->name }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card card-body space-y-2 text-sm">
                <div class="flex justify-between"><span>{{ __('Stitching') }}</span><span>{{ number_format($order->stitching_charges, 2) }}</span></div>
                @foreach ($order->extras as $extra)
                    <div class="flex justify-between text-slate-600"><span>{{ $extra->description }}</span><span>{{ number_format($extra->amount, 2) }}</span></div>
                @endforeach
                <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><span>{{ __('Total') }}</span><span>{{ number_format($order->total, 2) }}</span></div>
                <div class="flex justify-between"><span>{{ __('Paid') }}</span><span>{{ number_format($order->paid_amount, 2) }}</span></div>
                <div @class(['flex justify-between text-base font-semibold', 'text-rose-600' => $order->balanceDue() > 0])><span>{{ __('Balance') }}</span><span>{{ number_format($order->balanceDue(), 2) }}</span></div>
                @if ($order->payments->isNotEmpty())
                    <div class="border-t border-slate-100 pt-2 text-xs text-slate-500">
                        @foreach ($order->payments as $payment)
                            <div @class(['flex justify-between', 'line-through' => $payment->cancelled_at])>
                                <span>{{ $payment->payment_date->translatedFormat('d M') }} · {{ $payment->method->label() }} · {{ $payment->number }}</span>
                                <span>{{ $payment->direction === 'out' ? '−' : '' }}{{ number_format($payment->amount, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($open)
                @can('orders.update_status')
                    <form method="POST" action="{{ route('orders.status', $order) }}" class="card card-body space-y-3">
                        @csrf @method('PATCH')
                        <h2 class="font-semibold">{{ __('Update status') }}</h2>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($statuses as $status)
                                <button name="status" value="{{ $status->value }}" @class(['btn btn-sm', 'btn-primary' => $order->status === $status, 'btn-secondary' => $order->status !== $status])>{{ $status->label() }}</button>
                            @endforeach
                        </div>
                        <input name="notes" class="input" placeholder="{{ __('Note (optional)') }}">
                    </form>
                @endcan

                @can('orders.manage')
                    <form method="POST" action="{{ route('orders.deliver', $order) }}" class="card card-body space-y-3" data-confirm="Mark {{ $order->number }} as delivered?">
                        @csrf
                        <h2 class="font-semibold">{{ __('Deliver to customer') }}</h2>
                        <x-field name="amount" :label="__('Collect now')" type="number" step="1" min="0" :value="max($order->balanceDue(), 0)" />
                        <x-select name="method" :label="__('Paid by')" :options="$methodOptions" selected="cash" />
                        <p class="text-xs text-slate-500">{{ __('Anything not collected stays on the customer\'s account.') }}</p>
                        <button class="btn btn-primary">{{ __('Deliver') }}</button>
                    </form>
                @endcan

                @can('payments.receive')
                    @if ($order->balanceDue() > 0)
                        <form method="POST" action="{{ route('orders.payments.store', $order) }}" class="card card-body space-y-3">
                            @csrf
                            <h2 class="font-semibold">{{ __('Receive advance / payment') }}</h2>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" step="1" min="1" max="{{ $order->balanceDue() }}" name="amount" class="input" placeholder="{{ __('Amount') }}" required>
                                <select name="method" class="input">
                                    @foreach ($methodOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn btn-secondary">{{ __('Save payment') }}</button>
                        </form>
                    @endif
                @endcan

                @can('orders.assign')
                    <form method="POST" action="{{ route('orders.assignments.store', $order) }}" class="card card-body space-y-3">
                        @csrf
                        <h2 class="font-semibold">{{ __('Give to tailor') }}</h2>
                        @if ($tailors->isEmpty())
                            <p class="text-xs text-slate-500">{{ __('Add users with the Tailor role to assign work.') }}</p>
                        @else
                            <x-select name="tailor_id" :label="__('Tailor')" :options="$tailors->pluck('name', 'id')" :selected="$order->tailor_id" required />
                            <div class="grid grid-cols-2 gap-2">
                                <x-field name="pieces_given" :label="__('Pieces')" type="number" min="1" :value="$order->pieces" required />
                                <x-field name="rate_per_piece" :label="__('Rate / piece')" type="number" step="1" min="0" />
                            </div>
                            <button class="btn btn-secondary">{{ __('Assign') }}</button>
                        @endif
                    </form>
                @endcan

                @can('orders.manage')
                    <form method="POST" action="{{ route('orders.cancel', $order) }}" class="card card-body space-y-3" data-confirm="Cancel {{ $order->number }}? Charges are reversed.">
                        @csrf @method('PATCH')
                        <h2 class="font-semibold text-rose-700">{{ __('Cancel order') }}</h2>
                        <input name="reason" class="input" placeholder="{{ __('Reason') }}" required>
                        @if ((float) $order->paid_amount > 0)
                            <select name="refund_method" class="input">
                                <option value="">{{ __('Keep') }} {{ number_format($order->paid_amount, 0) }} {{ __('as customer credit') }}</option>
                                @foreach ($methodOptions as $value => $label)
                                    <option value="{{ $value }}">{{ __('Refund') }} {{ number_format($order->paid_amount, 0) }} {{ __('by') }} {{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                        <button class="btn btn-danger">{{ __('Cancel order') }}</button>
                    </form>
                @endcan
            @endif

            @if ($order->assignments->isNotEmpty())
                <div class="card overflow-x-auto">
                    <div class="border-b border-slate-200 px-4 py-3"><h2 class="font-semibold">{{ __('Tailor work') }}</h2></div>
                    <table class="table">
                        <tbody>
                        @foreach ($order->assignments as $assignment)
                            <tr>
                                <td>
                                    <div class="font-medium">{{ $assignment->tailor->name }}</div>
                                    <div class="text-xs text-slate-500">{{ __('Given') }} {{ $assignment->pieces_given }} {{ __('on') }} {{ $assignment->given_at->translatedFormat('d M') }}{{ $assignment->rate_per_piece ? ' · '.number_format($assignment->rate_per_piece, 0).'/piece' : '' }}</div>
                                    <div class="text-xs">{{ __('Received') }} {{ $assignment->pieces_received }}{{ $assignment->received_at ? ' on '.$assignment->received_at->translatedFormat('d M') : '' }}{{ $assignment->rate_per_piece ? ' · earns '.number_format($assignment->earnings(), 0) : '' }}</div>
                                </td>
                                <td class="text-end">
                                    @can('orders.assign')
                                        @if ($assignment->pieces_received < $assignment->pieces_given)
                                            <form method="POST" action="{{ route('assignments.receive', $assignment) }}" class="flex gap-1">
                                                @csrf @method('PATCH')
                                                <input type="number" name="pieces" min="1" max="{{ $assignment->pieces_given - $assignment->pieces_received }}" value="{{ $assignment->pieces_given - $assignment->pieces_received }}" class="input w-16 py-1">
                                                <button class="btn btn-secondary btn-sm">{{ __('Received') }}</button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($order->alterations->isNotEmpty())
                <div class="card card-body text-sm">
                    <h2 class="mb-2 font-semibold">{{ __('Alterations') }}</h2>
                    @foreach ($order->alterations as $alteration)
                        <div><a href="{{ route('orders.show', $alteration) }}" class="text-indigo-600 hover:underline">{{ $alteration->number }}</a> · {{ $alteration->status->label() }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
