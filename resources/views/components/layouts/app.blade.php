@props(['title' => null, 'pos' => false, 'heading' => true])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Installable as a desktop app (Chrome / Edge: Install button in the address bar). --}}
    <link rel="manifest" href="{{ route('manifest') }}">
    <meta name="theme-color" content="#475569">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="idle-timeout" content="{{ $idleTimeoutMinutes }}">
    <meta name="phone-check-url" content="{{ route('phone.check') }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $shopName }}</title>
    @include('partials.locale-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Phrases used by app.js, in the user's language. --}}
    @php
        $jsTranslations = collect([
            'No matching products', 'Add :count selected', 'Tick colours to add', 'All :count colours', ':sku · stock :stock', 'Select all',
            ':label (:sku) · current stock :stock', ':label · :sku · stock :stock', 'No matches', 'Balance :amount',
            'This phone already belongs to :name.', 'This phone already belongs to :name (deleted customer).', 'Open that customer',
            'Walk-in customer', ':percent% discount', 'balance :amount', 'Family member: :member',
            'The same phone number is entered twice on this form.', 'Add at least one item to the bill.',
            'Remove all items from this bill?', 'Start a new bill? Items on this bill will be removed.', 'Price label', 'Normal price :price', 'Customer found',
            'Leave this bill to see the customer? Items on this bill will be removed. Use Hold bill to keep them.',
            'Open bill :number? Items on this bill will be removed. Use Hold bill to keep them.', 'Bill',
            'No internet. Bills are saved on this computer and upload when the internet is back.',
            ':count bill(s) waiting to upload.', 'Upload now', 'Try again', 'Remove', 'Not uploaded',
            'The server did not accept this bill.', 'Log in again to upload the saved bills.',
            'Upload stopped; it will try again shortly.', ':count offline bill(s) uploaded.', 'Add at least one item.',
            'This computer has no space to save the bill. Remove old saved bills first.',
            'Holding a bill needs the internet. Complete the sale instead; it will upload later.',
            'Bill saved on this computer. It will upload when the internet is back.',
            'Remove this saved bill? It will not be uploaded.', 'Net total',
            'Offline bill. The invoice number is given when it uploads.',
        ])->mapWithKeys(fn ($key) => [$key => __($key)]);
    @endphp
    <script>window.translations = @json($jsTranslations);</script>
</head>
<body @class(['flex min-h-screen flex-col bg-slate-100 font-sans text-slate-900 antialiased', 'lg:h-screen lg:overflow-hidden' => $pos])>
@php
    $navItems = [
        ['heading' => 'Counter', 'items' => [
            ['New sale', 'sales.create', 'sales.create', 'sales.create'],
            ['Dashboard', 'dashboard', 'dashboard', null],
            ['Sales', 'sales.index', ['sales.index', 'sales.show', 'sale-returns.*'], 'sales.view'],
            ['Customers', 'customers.index', ['customers.*', 'measurements.*'], 'customers.view'],
        ]],
        ['heading' => 'Stitching', 'items' => [
            ['Due list', 'orders.due', 'orders.due', 'orders.view'],
            ['Orders', 'orders.index', ['orders.index', 'orders.show', 'orders.create', 'orders.edit'], 'orders.view-any'],
            ['Tailors', 'tailors.index', 'tailors.*', 'orders.assign'],
        ]],
        ['heading' => 'Stock', 'items' => [
            ['Products', 'products.index', 'products.*', 'products.view'],
            ['Low stock', 'stock.low', 'stock.low', 'products.view'],
            ['Barcode generator', 'barcode-labels.index', 'barcode-labels.*', 'products.manage'],
            ...(\App\Models\Setting::usesBarcodes() ? [['Print labels', 'labels.create', 'labels.*', 'products.manage']] : []),
        ]],
        ['heading' => 'Purchasing', 'items' => [
            ['Purchases', 'purchases.index', 'purchases.*', 'purchases.view'],
            ['Suppliers', 'suppliers.index', 'suppliers.*', 'suppliers.view'],
        ]],
        ['heading' => 'Accounts', 'items' => [
            ['Receivables', 'receivables.index', 'receivables.*', 'receivables.view'],
            ['Profit summary', 'profit.index', 'profit.*', 'cost.view'],
            ...(\App\Models\Setting::usesMessaging() ? [['Message log', 'notifications.index', 'notifications.*', 'messages.send']] : []),
        ]],
        ['heading' => 'Shop', 'items' => [
            ['Measurement fields', ['measurement-fields.index', 'gents'], 'measurement-fields.*', 'measurements.configure'],
            ['Staff', 'staff.index', 'staff.*', 'users.manage'],
            ['My profile', 'profile.edit', 'profile.*', null],
            ['Settings', 'settings.edit', 'settings.edit', 'settings.manage'],
            ...(\App\Models\Setting::usesMessaging() ? [['SMS / WhatsApp', 'messaging.settings', 'messaging.*', 'settings.manage']] : []),
        ]],
    ];

    $icons = [
        'Dashboard' => 'home', 'New sale' => 'cart', 'Sales' => 'receipt', 'Customers' => 'users',
        'Due list' => 'calendar', 'Orders' => 'scissors', 'Tailors' => 'ruler',
        'Receivables' => 'inbox', 'Profit summary' => 'chart', 'Message log' => 'chat',
        'Products' => 'cube', 'Low stock' => 'warning', 'Print labels' => 'tag', 'Barcode generator' => 'tag',
        'Purchases' => 'truck', 'Suppliers' => 'store', 'Measurement fields' => 'ruler',
        'Staff' => 'user-plus', 'My profile' => 'user', 'Settings' => 'cog', 'SMS / WhatsApp' => 'chat',
    ];

    $user = auth()->user();
@endphp

<header class="no-print sticky top-0 z-30 bg-indigo-600 text-white shadow-md">
    <div class="flex items-center gap-2 px-3 py-2.5 md:gap-3 md:px-4">
        <a href="{{ route('dashboard') }}" class="hidden shrink-0 items-center gap-2 pe-1 md:flex" title="{{ __('Dashboard') }}">
            @if ($headerLogoUrl = \App\Models\Setting::headerLogoUrl())
                <img src="{{ $headerLogoUrl }}" alt="" class="h-10 max-w-36 rounded-md bg-white object-contain p-0.5">
            @else
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-base font-bold text-indigo-600">{{ mb_strtoupper(mb_substr($shopName, 0, 1)) }}</span>
            @endif
            <span class="max-w-40 truncate text-lg font-semibold">{{ $shopName }}</span>
        </a>

        <button type="button" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-white/15 px-3 py-2.5 text-sm font-semibold hover:bg-white/25" data-menu-open aria-haspopup="dialog">
            <x-icon name="squares" class="h-5 w-5" />
            <span class="hidden sm:inline">{{ __('Menu') }}</span>
        </button>

        <nav class="hidden shrink-0 items-center gap-1 lg:flex">
            @can('sales.create')
                <a href="{{ route('sales.create') }}" @class(['inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium', 'bg-white text-indigo-800' => request()->routeIs('sales.create'), 'hover:bg-white/15' => ! request()->routeIs('sales.create')])>
                    <x-icon name="cart" class="h-5 w-5" /> {{ __('New sale') }}
                </a>
            @endcan
            <a href="{{ route('dashboard') }}" @class(['inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium', 'bg-white text-indigo-800' => request()->routeIs('dashboard'), 'hover:bg-white/15' => ! request()->routeIs('dashboard')])>
                <x-icon name="home" class="h-5 w-5" /> {{ __('Dashboard') }}
            </a>
        </nav>

        @can('customers.view')
            <div class="relative mx-auto w-full max-w-xl flex-1">
                <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                <input type="search" class="input h-11 rounded-lg border-transparent ps-11 text-base" placeholder="{{ __('Find customer by phone or name (F2)') }}" autocomplete="off"
                       data-global-customer-search data-url="{{ route('customers.lookup') }}">
                <div data-search-results class="absolute z-40 mt-1 hidden max-h-[28rem] w-full overflow-y-auto rounded-lg border border-slate-200 bg-white text-base text-slate-900 shadow-lg [&>button]:py-2.5" role="listbox"></div>
            </div>
        @else
            <div class="flex-1"></div>
        @endcan

        <div class="flex shrink-0 items-center gap-2 text-sm md:gap-3">
            {{-- Shown only when the browser allows installing (Chrome / Edge) and the app is not installed yet. --}}
            <button type="button" class="hidden items-center gap-1.5 rounded-lg bg-white px-3 py-2 font-semibold text-slate-800 shadow-sm hover:bg-slate-100" data-install-app title="{{ __('Install as a desktop app with its own icon') }}">
                <x-icon name="cart" class="h-5 w-5" /> <span class="hidden sm:inline">{{ __('Install app') }}</span>
            </button>
            <x-locale-switch />
            <a href="{{ route('profile.edit') }}" class="hidden items-center gap-1.5 rounded-lg px-2 py-2 font-medium hover:bg-white/15 sm:inline-flex" title="{{ __('My profile') }}">
                <x-icon name="user" class="h-5 w-5" /> <span class="max-w-32 truncate">{{ $user->name }}</span>
            </a>
        </div>
    </div>
</header>

{{-- Full-screen tile menu: every section of the system, one tap away. --}}
<div class="no-print fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto bg-slate-900/60 p-3 md:p-8" data-menu role="dialog" aria-modal="true" aria-label="{{ __('Menu') }}">
    <div class="w-full max-w-7xl rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold">{{ __('Menu') }}</h2>
            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" data-menu-close aria-label="{{ __('Close') }}"><x-icon name="x-mark" class="h-6 w-6" /></button>
        </div>
        <div class="grid gap-6 p-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($navItems as $section)
                @php
                    $visible = collect($section['items'])->filter(fn ($item) => $item[3] === null || $user->can($item[3]));
                @endphp
                @if ($visible->isNotEmpty())
                    <section>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __($section['heading']) }}</h3>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($visible as $item)
                                @php
                                    [$label, $routeName, $pattern] = $item;
                                    $url = is_array($routeName) ? route($routeName[0], $routeName[1]) : route($routeName);
                                    $active = request()->routeIs($pattern);
                                @endphp
                                <x-menu-card :href="$url" :label="$label" :icon="$icons[$label] ?? 'squares'" :active="$active" />
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
        </div>
        <div class="flex justify-end border-t border-slate-200 px-5 py-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn inline-flex items-center gap-2 bg-rose-600 px-5 py-2.5 text-white hover:bg-rose-700">
                    <x-icon name="logout" class="h-5 w-5 rtl:-scale-x-100" /> {{ __('Log out') }}
                </button>
            </form>
        </div>
    </div>
</div>

<main @class(['flex-1', 'min-h-0 p-3' => $pos, 'px-4 py-6 md:px-6' => ! $pos])>
    @if ($title && ! $pos && $heading)
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold text-slate-900">{{ $title }}</h1>
            @isset($actions)
                <div class="no-print flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    @foreach (['success' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'error' => 'border-rose-200 bg-rose-50 text-rose-800'] as $key => $classes)
        @if (session($key))
            <div class="no-print mb-3 rounded-md border px-4 py-3 text-sm {{ $classes }}">{{ session($key) }}</div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="no-print mb-3 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <div class="font-medium">{{ __('Please fix the following:') }}</div>
            <ul class="mt-1 list-disc ps-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{ $slot }}
</main>

<footer class="no-print bg-indigo-700 text-sm text-indigo-50">
    <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-1 px-4 py-2">
        <div class="flex items-center gap-3">
            <span>{{ now()->translatedFormat('l, d F Y') }}</span>
            <span class="font-semibold tabular-nums" data-clock>{{ now()->format('H:i') }}</span>
        </div>
        <div>{{ __('User') }}: <span class="font-semibold">{{ $user->name }}</span> · {{ __($user->role?->name ?? 'Owner') }}</div>
        <div class="flex items-center gap-3">
            <span class="hidden sm:inline">{{ $shopName }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 hover:bg-white/15" title="{{ __('Log out') }}">
                    <x-icon name="logout" class="h-4 w-4 rtl:-scale-x-100" /> {{ __('Log out') }}
                </button>
            </form>
        </div>
    </div>
    <form id="idle-logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
        <input type="hidden" name="reason" value="idle">
    </form>
</footer>
</body>
</html>
