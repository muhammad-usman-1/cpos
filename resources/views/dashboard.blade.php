<x-layouts.app :title="__('Dashboard')">
    @php
        $quickActions = collect([
            ['New sale', 'sales.create', 'cart', 'sales.create', true],
            ['New stitching order', 'orders.create', 'scissors', 'orders.manage', false],
            ['New customer', 'customers.create', 'users', 'customers.manage', false],
            ['New purchase', 'purchases.create', 'truck', 'purchases.manage', false],
            ['Add product', 'products.create', 'cube', 'products.manage', false],
            ['Due list', 'orders.due', 'calendar', 'orders.view', false],
            ['Orders', 'orders.index', 'scissors', 'orders.view-any', false],
            ['Tailors', 'tailors.index', 'ruler', 'orders.assign', false],
            ['Receivables', 'receivables.index', 'inbox', 'receivables.view', false],
            ['Today\'s sales', ['sales.index', ['from' => today()->toDateString()]], 'receipt', 'sales.view', false],
        ])->filter(fn ($action) => auth()->user()->can($action[3]));
    @endphp

    @if (auth()->user()->isManager())
        <div class="mb-6 rounded-xl border border-slate-200 bg-white px-5 py-4">
            <div class="text-lg font-semibold text-slate-900">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</div>
            <div class="text-sm text-slate-500">{{ __('Manager') }} · {{ today()->translatedFormat('l, d F Y') }}</div>
        </div>
    @endif

    @if ($quickActions->isNotEmpty())
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-indigo-700">{{ __('Quick actions') }}</h2>
        {{-- One row; boxes share the width equally and the row scrolls sideways on small screens. --}}
        <div class="mb-8 grid auto-cols-[minmax(8.5rem,1fr)] grid-flow-col gap-3 overflow-x-auto pb-1">
            @foreach ($quickActions as [$label, $routeName, $icon, $permission, $isPrimary])
                <x-menu-card :href="is_array($routeName) ? route($routeName[0], $routeName[1]) : route($routeName)" :label="$label" :icon="$icon" class="min-h-32" />
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @isset($stats['sales_today'])
            <a href="{{ route('sales.index', ['from' => today()->toDateString()]) }}" class="card card-body border-t-4 border-t-indigo-600 hover:bg-indigo-50">
                <div class="text-sm font-medium text-indigo-700">{{ __('Sales today') }}</div>
                <div class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($stats['sales_today'], 2) }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ trans_choice(':count bill|:count bills', $stats['bills_today'], ['count' => $stats['bills_today']]) }}</div>
            </a>
        @endisset
        @isset($stats['receivable'])
            <a href="{{ route('customers.index', ['filter' => 'balance']) }}" class="card card-body border-t-4 border-t-indigo-600 hover:bg-indigo-50">
                <div class="text-sm font-medium text-indigo-700">{{ __('Customers owe (receivable)') }}</div>
                <div class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($stats['receivable'], 2) }}</div>
            </a>
        @endisset
        @isset($stats['orders_due_today'])
            <a href="{{ auth()->user()->can('orders.view') ? route('orders.due') : route('orders.index') }}" class="card card-body border-t-4 border-t-indigo-600 hover:bg-indigo-50">
                <div class="text-sm font-medium text-indigo-700">{{ __('Orders due today / ready') }}</div>
                <div class="mt-1 text-3xl font-bold text-slate-900">{{ $stats['orders_due_today'] }} <span class="text-base font-normal text-slate-500">/ {{ $stats['orders_ready'] }} {{ __('ready') }}</span></div>
            </a>
            <a href="{{ auth()->user()->can('orders.view') ? route('orders.due').'#overdue' : route('orders.index') }}" @class(['card card-body border-t-4 border-t-indigo-600 hover:bg-indigo-50', 'border-rose-300 bg-rose-50' => $stats['orders_overdue'] > 0])>
                <div class="text-sm font-medium text-indigo-700">{{ __('Overdue orders') }}</div>
                <div @class(['mt-1 text-2xl font-semibold', 'text-rose-600' => $stats['orders_overdue'] > 0])>{{ $stats['orders_overdue'] }}</div>
            </a>
            <a href="{{ auth()->user()->can('orders.assign') ? route('tailors.index') : route('orders.index') }}" class="card card-body border-t-4 border-t-indigo-600 hover:bg-indigo-50">
                <div class="text-sm font-medium text-indigo-700">{{ __('Orders with tailors') }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ $stats['orders_with_tailors'] }}</div>
            </a>
        @endisset
        @isset($stats['products'])
            <div class="card card-body border-t-4 border-t-indigo-600">
                <div class="text-sm font-medium text-indigo-700">{{ __('Active products') }}</div>
                <div class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($stats['products']) }}</div>
            </div>
            <a href="{{ route('stock.low') }}" class="card card-body border-t-4 border-t-indigo-600 hover:bg-indigo-50">
                <div class="text-sm font-medium text-indigo-700">{{ __('Low stock items') }}</div>
                <div @class(['mt-1 text-2xl font-semibold', 'text-amber-600' => $stats['low_stock'] > 0])>{{ number_format($stats['low_stock']) }}</div>
            </a>
        @endisset
        @isset($stats['stock_value'])
            <div class="card card-body border-t-4 border-t-indigo-600">
                <div class="text-sm font-medium text-indigo-700">{{ __('Stock value at average cost') }}</div>
                <div class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($stats['stock_value'], 2) }}</div>
            </div>
        @endisset
        @isset($stats['payable'])
            <a href="{{ route('suppliers.index', ['status' => 'payable']) }}" class="card card-body border-t-4 border-t-indigo-600 hover:bg-indigo-50">
                <div class="text-sm font-medium text-indigo-700">{{ __('Total payable to suppliers') }}</div>
                <div class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($stats['payable'], 2) }}</div>
            </a>
        @endisset
        @isset($stats['purchases_today'])
            <div class="card card-body border-t-4 border-t-indigo-600">
                <div class="text-sm font-medium text-indigo-700">{{ __('Purchases today') }}</div>
                <div class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($stats['purchases_today'], 2) }}</div>
            </div>
        @endisset
    </div>

    @if ($ordersDue->isNotEmpty() || $recentSales->isNotEmpty())
        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            @if ($ordersDue->isNotEmpty())
                <div class="card overflow-x-auto">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                        <h2 class="font-semibold">{{ __('Stitching orders due') }}</h2>
                        <a href="{{ auth()->user()->can('orders.view') ? route('orders.due') : route('orders.index') }}" class="text-sm text-indigo-600 hover:underline">{{ __('View all') }}</a>
                    </div>
                    <table class="table">
                        <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Due') }}</th><th>{{ __('Tailor') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                        @foreach ($ordersDue as $order)
                            <tr data-href="{{ route('orders.show', $order) }}">
                                <td class="font-medium">{{ $order->number }}</td>
                                <td>{{ $order->customer?->name }}</td>
                                <td @class(['whitespace-nowrap', 'font-semibold text-rose-600' => $order->isOverdue()])>{{ $order->promised_date->format('d M') }}</td>
                                <td>{{ $order->tailor?->name ?? '—' }}</td>
                                <td><span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($recentSales->isNotEmpty())
                <div class="card overflow-x-auto">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                        <h2 class="font-semibold">{{ __('Recent sales') }}</h2>
                        <a href="{{ route('sales.index') }}" class="text-sm text-indigo-600 hover:underline">{{ __('View all') }}</a>
                    </div>
                    <table class="table">
                        <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Time') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                        <tbody>
                        @foreach ($recentSales as $sale)
                            <tr data-href="{{ route('sales.show', $sale) }}">
                                <td class="font-medium">{{ $sale->number }}</td>
                                <td>{{ $sale->customer?->name ?? __('Walk-in customer') }}</td>
                                <td class="whitespace-nowrap text-slate-500">{{ ($sale->completed_at ?? $sale->sale_date)->format($sale->sale_date->isToday() ? 'h:i A' : 'd M, h:i A') }}</td>
                                <td class="text-end font-medium">{{ number_format((float) $sale->total, 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    @if ($lowStock->isNotEmpty())
        <div class="card mt-6">
            <div class="flex items-center justify-between rounded-t-lg bg-indigo-600 px-5 py-3 text-white">
                <h2 class="font-semibold">{{ __('Low stock alert') }}</h2>
                <a href="{{ route('stock.low') }}" class="text-sm text-white hover:underline">{{ __('View all') }}</a>
            </div>
            <table class="table">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('SKU') }}</th><th class="text-end">{{ __('In stock') }}</th><th class="text-end">{{ __('Minimum') }}</th></tr></thead>
                <tbody>
                @foreach ($lowStock as $variant)
                    <tr>
                        <td><a href="{{ route('products.show', $variant->product) }}" class="text-indigo-600 hover:underline">{{ $variant->displayName() }}</a></td>
                        <td>{{ $variant->sku }}</td>
                        <td class="text-end font-medium text-rose-600">{{ (float) $variant->stock_qty }}</td>
                        <td class="text-end">{{ (float) $variant->min_stock }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
