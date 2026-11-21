{{-- Expects $orders (collection or paginator). --}}
<table class="table">
    <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Customer') }}</th><th>{{ __('For') }}</th><th>{{ __('Promised') }}</th><th>{{ __('Status') }}</th><th>{{ __('Tailor') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
    <tbody>
    @forelse ($orders as $order)
        <tr @class(['bg-rose-50' => $order->isOverdue()]) data-href="{{ route('orders.show', $order) }}">
            <td class="whitespace-nowrap">
                <a href="{{ route('orders.show', $order) }}" class="font-medium text-indigo-600 hover:underline">{{ $order->number }}</a>
                @if ($order->type->value === 'alteration')<span class="badge badge-gray">{{ __('Alteration') }}</span>@endif
            </td>
            <td>{{ $order->customer->name }}<div class="font-mono text-xs text-slate-500">{{ $order->customer->phone }}</div></td>
            <td>{{ $order->profile->label() }}</td>
            <td @class(['whitespace-nowrap', 'font-semibold text-rose-600' => $order->isOverdue()])>
                {{ $order->promised_date->translatedFormat('D d M') }}
                @if ($order->isOverdue())<div class="text-xs">{{ $order->promised_date->diffForHumans(today(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }} {{ __('late') }}</div>@endif
            </td>
            <td><span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
            <td class="text-xs">{{ $order->tailor?->name }}</td>
            <td class="text-end">{{ number_format($order->total, 2) }}</td>
            <td @class(['text-end', 'font-semibold text-rose-600' => $order->balanceDue() > 0])>{{ number_format($order->balanceDue(), 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="py-6 text-center text-slate-500">{{ __('No orders.') }}</td></tr>
    @endforelse
    </tbody>
</table>
