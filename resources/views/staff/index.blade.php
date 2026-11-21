<x-layouts.app :title="__('Staff')">
    <p class="mb-4 text-sm text-slate-600">{{ __('Managers log in with their own username and password. They can only use the parts of the shop listed below.') }}</p>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 self-start">
            <form method="POST" action="{{ route('staff.store') }}" class="card card-body space-y-3">
                @csrf
                <h2 class="font-semibold">{{ __('Add manager') }}</h2>
                <x-field name="name" :label="__('Name')" required />
                <x-field name="username" :label="__('Username')" required autocomplete="off" :hint="__('Letters, numbers and dashes. Used to log in.')" />
                <x-field name="phone" :label="__('Phone')" inputmode="tel" autocomplete="off" data-phone-check data-ignore-type="user" />
                <x-field name="password" type="password" :label="__('Password')" required autocomplete="new-password" :hint="__('At least 6 characters.')" />
                <button class="btn btn-primary w-full">{{ __('Add manager') }}</button>
            </form>

            <div class="card card-body">
                <h2 class="mb-3 font-semibold">{{ __('What a manager can do') }}</h2>
                <div class="space-y-3 text-sm">
                    @forelse ($permissions as $group => $groupPermissions)
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __($group) }}</div>
                            <ul class="mt-1 space-y-1">
                                @foreach ($groupPermissions as $permission)
                                    <li class="flex gap-2"><x-icon name="check" class="h-4 w-4 shrink-0 text-emerald-600" /> {{ __($permission->label) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <p class="text-slate-500">{{ __('Nothing allowed yet.') }}</p>
                    @endforelse
                </div>
                <p class="mt-4 text-xs text-slate-500">{{ __('Managers cannot buy stock, see suppliers or cost prices, add staff or change settings.') }}</p>
            </div>
        </div>

        <div class="card overflow-x-auto self-start lg:col-span-2">
            <table class="table">
                <thead><tr><th>{{ __('Manager') }}</th><th>{{ __('Last login') }}</th></tr></thead>
                <tbody>
                @forelse ($managers as $manager)
                    <tr @class(['bg-slate-50' => ! $manager->is_active])>
                        <td>
                            <form method="POST" action="{{ route('staff.update', $manager) }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                @csrf @method('PUT')
                                <label class="block"><span class="text-xs text-slate-500">{{ __('Name') }}</span>
                                    <input name="name" value="{{ $manager->name }}" class="input" required></label>
                                <label class="block"><span class="text-xs text-slate-500">{{ __('Username') }}</span>
                                    <input name="username" value="{{ $manager->username }}" class="input" required autocomplete="off"></label>
                                <label class="block"><span class="text-xs text-slate-500">{{ __('Phone') }}</span>
                                    <input name="phone" value="{{ $manager->phone }}" class="input" inputmode="tel" autocomplete="off" data-phone-check data-ignore-type="user" data-ignore-id="{{ $manager->id }}"></label>
                                <label class="block"><span class="text-xs text-slate-500">{{ __('New password') }}</span>
                                    <input name="password" type="password" class="input" autocomplete="new-password" placeholder="{{ __('Leave blank to keep') }}"></label>
                                <div class="flex items-center gap-4 sm:col-span-2 xl:col-span-4">
                                    <label class="inline-flex items-center gap-1 text-sm"><input type="checkbox" name="is_active" value="1" @checked($manager->is_active)> {{ __('Can log in') }}</label>
                                    @unless ($manager->is_active)
                                        <span class="badge badge-gray">{{ __('Deactivated') }}</span>
                                    @endunless
                                    <button class="btn btn-secondary btn-sm ms-auto">{{ __('Save') }}</button>
                                </div>
                            </form>
                        </td>
                        <td class="whitespace-nowrap align-top text-sm text-slate-500">{{ $manager->last_login_at?->format('d M Y, h:i A') ?? __('Never') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="py-6 text-center text-slate-500">{{ __('No managers yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
