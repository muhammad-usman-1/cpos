<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Developer login · {{ \App\Models\Setting::get('shop_name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 px-4 font-sans">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            @if ($headerLogoUrl = \App\Models\Setting::headerLogoUrl())
                <img src="{{ $headerLogoUrl }}" alt="" class="mx-auto mb-3 max-h-20 max-w-56 object-contain">
            @else
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-white"><x-icon name="shield" class="h-7 w-7" /></div>
            @endif
            <h1 class="text-2xl font-semibold text-slate-900">Developer login</h1>
            <p class="mt-1 text-sm text-slate-500">For resetting the shop owner's password.</p>
        </div>

        <div class="card card-body">
            @if (session('status'))
                <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ session('status') }}</div>
            @endif

            @if (! $isConfigured)
                <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-3 text-sm text-rose-800">
                    Developer access is not set up yet. On the server run:
                    <code class="mt-2 block rounded bg-white px-2 py-1 font-mono text-xs">php artisan developer:password</code>
                </div>
            @else
                <form method="POST" action="{{ route('developer.login') }}" class="space-y-4">
                    @csrf
                    <x-field name="username" label="Developer username" required autofocus autocomplete="off" />
                    <x-field name="password" label="Password" type="password" required autocomplete="off" />
                    <button class="btn btn-primary w-full py-3 text-base">Log in</button>
                </form>
            @endif
        </div>

        <p class="mt-4 text-center text-sm"><a href="{{ route('login') }}" class="text-indigo-600 hover:underline">← Back to the shop login</a></p>
    </div>
</body>
</html>
