@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @include('partials.locale-head')
    @vite(['resources/css/app.css'])
    {{ $head ?? '' }}
</head>
<body class="bg-white font-sans text-slate-900">
    <div class="no-print flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-6 py-3">
        <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">{{ __('Print') }}</button>
        <button type="button" onclick="history.back()" class="btn btn-secondary btn-sm">{{ __('Back') }}</button>
    </div>
    {{ $slot }}
</body>
</html>
