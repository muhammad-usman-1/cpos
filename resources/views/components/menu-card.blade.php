@props(['href', 'label', 'icon' => 'squares', 'active' => false])

{{-- White card with a coloured icon, the label and a short hint, used by the menu and the dashboard. --}}
@php
    $styles = [
        'New sale' => ['bg-emerald-500', 'Start a bill'],
        'Dashboard' => ['bg-sky-500', 'Today at a glance'],
        'Sales' => ['bg-amber-500', 'Bills and returns'],
        'Today\'s sales' => ['bg-amber-500', 'Bills made today'],
        'Customers' => ['bg-blue-600', 'People and measurements'],
        'New customer' => ['bg-blue-600', 'Add a customer'],
        'Due list' => ['bg-rose-500', 'Orders due soon'],
        'Orders' => ['bg-pink-500', 'Stitching orders'],
        'New stitching order' => ['bg-pink-500', 'Book an order'],
        'Tailors' => ['bg-fuchsia-600', 'Work and payments'],
        'Products' => ['bg-teal-500', 'Items and stock'],
        'Add product' => ['bg-teal-500', 'New item'],
        'Low stock' => ['bg-orange-500', 'Running out'],
        'Barcode generator' => ['bg-slate-700', 'Make barcodes'],
        'Print labels' => ['bg-slate-700', 'Price tags'],
        'Purchases' => ['bg-cyan-600', 'Stock bought'],
        'New purchase' => ['bg-cyan-600', 'Record stock bought'],
        'Suppliers' => ['bg-lime-600', 'Who you buy from'],
        'Receivables' => ['bg-red-500', 'Money to collect'],
        'Profit summary' => ['bg-green-600', 'Earnings report'],
        'Message log' => ['bg-green-500', 'Sent messages'],
        'SMS / WhatsApp' => ['bg-green-500', 'Message setup'],
        'Measurement fields' => ['bg-yellow-500', 'Measurement forms'],
        'Staff' => ['bg-indigo-500', 'Manager logins'],
        'My profile' => ['bg-gray-500', 'Your account'],
        'Settings' => ['bg-gray-600', 'Shop and bill setup'],
    ];
    [$iconBackground, $hint] = $styles[$label] ?? ['bg-slate-500', null];
@endphp

<a href="{{ $href }}" {{ $attributes->class([
    'group flex flex-col items-center justify-center gap-2 rounded-xl border bg-white px-4 py-4 text-center transition select-none active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300',
    'border-slate-200 hover:border-slate-300 hover:bg-slate-50 hover:shadow-sm' => ! $active,
    'border-slate-400 bg-slate-50 shadow-sm' => $active,
]) }}>
    <span class="flex h-11 w-11 items-center justify-center rounded-xl {{ $iconBackground }} text-white shadow-sm transition group-hover:scale-105">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>
    <span class="text-sm leading-tight font-semibold text-slate-900">{{ __($label) }}</span>
    @if ($hint)
        <span class="-mt-1 text-xs leading-tight text-slate-500">{{ __($hint) }}</span>
    @endif
</a>
