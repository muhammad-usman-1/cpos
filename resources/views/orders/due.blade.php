<x-layouts.app :title="__('Due list')">
    <x-slot:actions>
        @can('orders.manage')
            <a href="{{ route('orders.create') }}" class="btn btn-primary">{{ __('New order') }}</a>
        @endcan
    </x-slot:actions>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($sections as $heading => $orders)
            <a href="#{{ Str::slug($heading) }}" @class(['card card-body hover:border-indigo-300', 'border-rose-300 bg-rose-50' => $heading === 'Overdue' && $orders->isNotEmpty()])>
                <div class="text-sm text-slate-500">{{ __($heading) }}</div>
                <div @class(['text-2xl font-semibold', 'text-rose-600' => $heading === 'Overdue' && $orders->isNotEmpty()])>{{ $orders->count() }}</div>
            </a>
        @endforeach
    </div>

    @foreach ($sections as $heading => $orders)
        <div id="{{ Str::slug($heading) }}" class="card mb-6 overflow-x-auto">
            <div class="border-b border-slate-200 px-5 py-3"><h2 @class(['font-semibold', 'text-rose-700' => $heading === 'Overdue'])>{{ __($heading) }} ({{ $orders->count() }})</h2></div>
            @include('orders._table', ['orders' => $orders])
        </div>
    @endforeach
</x-layouts.app>
