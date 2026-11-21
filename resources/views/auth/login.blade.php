<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Log in') }} · {{ \App\Models\Setting::get('shop_name') }}</title>
    @include('partials.locale-head')
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 px-4 font-sans">
    {{-- One bordered panel holding the language switch, logo, shop name and the form. --}}
    <div class="w-full max-w-md rounded-2xl border-2 border-indigo-200 bg-white p-6 shadow-lg sm:p-8">
        <div class="mb-4 flex justify-end"><x-locale-switch /></div>
        @if ($headerLogoUrl = \App\Models\Setting::headerLogoUrl())
            <img src="{{ $headerLogoUrl }}" alt="" class="mx-auto mb-3 max-h-24 max-w-64 object-contain">
        @endif
        <h1 class="mb-6 text-center text-2xl font-semibold text-slate-900">{{ \App\Models\Setting::get('shop_name') }}</h1>

        <div>
            @if (session('status'))
                <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <x-field name="login" :label="__('Username or phone')" required autofocus autocomplete="username" />
                <x-field name="password" :label="__('Password')" type="password" required autocomplete="current-password" />
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300">
                    {{ __('Remember me on this device') }}
                </label>
                <button class="btn btn-primary w-full py-3 text-base">{{ __('Log in') }}</button>
            </form>
        </div>
        <p class="mt-5 border-t border-slate-200 pt-4 text-center text-xs text-slate-500">{{ __('Forgot your password? Contact your software developer to set a new one.') }}</p>
    </div>
</body>
</html>
