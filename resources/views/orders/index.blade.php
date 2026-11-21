<x-layouts.app :title="__('Stitching orders')">
    <x-slot:actions>
        @can('orders.view')
            <a href="{{ route('orders.due') }}" class="btn btn-secondary">{{ __('Due list') }}</a>
        @endcan
        @can('orders.manage')
            <a href="{{ route('orders.create') }}" class="btn btn-primary">{{ __('New order') }}</a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="card card-body mb-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <x-field name="q" :label="__('Order no., phone or name')" :value="request('q')" />
        <x-select name="status" :label="__('Status')" :options="['open' => __('All open'), 'all' => __('Everything')] + collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" :selected="request('status', 'open')" />
        @can('orders.view')
            <x-select name="tailor_id" :label="__('Tailor')" :options="$tailors->pluck('name', 'id')" :selected="request('tailor_id')" :placeholder="__('All')" />
        @endcan
        <x-field name="from" :label="__('Promised from')" type="date" :value="request('from')" />
        <x-field name="to" :label="__('Promised to')" type="date" :value="request('to')" />
        <div class="flex items-end"><button class="btn btn-secondary w-full">{{ __('Filter') }}</button></div>
    </form>

    <div class="card overflow-x-auto">
        @include('orders._table', ['orders' => $orders])
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
</x-layouts.app>
