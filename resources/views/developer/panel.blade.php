<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Developer · {{ \App\Models\Setting::get('shop_name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900">
    <header class="bg-indigo-600 text-white shadow-md">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
            <div class="flex items-center gap-2 font-semibold">
                @if ($headerLogoUrl = \App\Models\Setting::headerLogoUrl())
                    <img src="{{ $headerLogoUrl }}" alt="" class="h-9 max-w-32 rounded-md bg-white object-contain p-0.5">
                @else
                    <x-icon name="shield" class="h-6 w-6" />
                @endif
                Developer · {{ \App\Models\Setting::get('shop_name') }}
            </div>
            <form method="POST" action="{{ route('developer.logout') }}">
                @csrf
                <button class="inline-flex items-center gap-1.5 rounded-md bg-white/15 px-3 py-1.5 text-sm hover:bg-white/25"><x-icon name="logout" class="h-4 w-4" /> Log out</button>
            </form>
        </div>
    </header>

    <main class="mx-auto max-w-3xl space-y-6 px-4 py-8">
        @if (session('success'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="card card-body">
            <h2 class="mb-3 font-semibold">Shop owner account</h2>
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                <div><dt class="text-slate-500">Name</dt><dd class="font-medium">{{ $owner->name }}</dd></div>
                <div><dt class="text-slate-500">Username</dt><dd class="font-mono">{{ $owner->username }}</dd></div>
                <div><dt class="text-slate-500">Phone</dt><dd class="font-mono">{{ $owner->phone ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Last login</dt><dd>{{ $owner->last_login_at?->format('d M Y H:i') ?? 'Never' }}</dd></div>
            </dl>
            @unless ($owner->is_active)
                <p class="mt-3 rounded bg-amber-50 px-3 py-2 text-sm text-amber-800">This account is switched off. Saving a new password switches it back on.</p>
            @endunless
        </div>

        <form method="POST" action="{{ route('developer.owner.update') }}" class="card card-body space-y-4">
            @csrf
            @method('PUT')
            <div>
                <h2 class="font-semibold">Set a new owner password</h2>
                <p class="text-sm text-slate-500">The old password stops working at once and "remember me" on other devices is signed out. Tell the owner the new password and ask them to change it from My profile.</p>
            </div>

            @if ($errors->any())
                <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <ul class="list-disc ps-5">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-3">
                <x-field name="username" label="Owner username" :value="$owner->username" required autocomplete="off" />
                <x-field name="password" label="New password" type="password" required autocomplete="new-password" hint="At least 8 characters." />
                <x-field name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
            </div>
            <button class="btn btn-primary px-6 py-2.5">Save new password</button>
        </form>
    </main>
</body>
</html>
