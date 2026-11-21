<x-layouts.app :title="__('New stitching order')">
    <div class="card card-body space-y-4">
        <p class="text-sm text-slate-600">{{ __('Find the customer by phone or name. Their family profiles and saved measurements open on the next screen.') }}</p>
        <div class="relative">
            <input type="search" class="input py-3 text-base" placeholder="{{ __('Phone or name') }}" autocomplete="off" autofocus
                   data-customer-picker data-url="{{ route('customers.lookup') }}" data-target="{{ route('orders.create', ['customer' => '__ID__']) }}">
            <div data-search-results class="absolute z-20 mt-1 hidden max-h-80 w-full overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg"></div>
        </div>
        @can('customers.manage')
            <a href="{{ route('customers.create') }}" class="text-sm text-indigo-600 hover:underline">{{ __('+ New customer') }}</a>
        @endcan
    </div>
</x-layouts.app>
