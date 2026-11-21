<x-layouts.app :title="__('My profile')">
    <form method="POST" action="{{ route('profile.update') }}" class="card card-body space-y-5">
        @csrf
        @method('PUT')

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" :label="__('Full name')" :value="$user->name" required />
            <x-field name="username" :label="__('Username')" :value="$user->username" required :hint="__('Letters, numbers, dashes. Used to log in.')" />
            <x-field name="phone" :label="__('Phone')" :value="$user->phone" inputmode="tel" autocomplete="off" :hint="__('Can also be used to log in.')"
                     data-phone-check data-ignore-type="user" data-ignore-id="{{ $user->id }}" />
            <x-field name="email" :label="__('Email')" type="email" :value="$user->email" />
        </div>

        <div class="rounded-md border border-slate-200 p-4">
            <h2 class="mb-1 font-semibold">{{ __('Change password') }}</h2>
            <p class="mb-4 text-xs text-slate-500">{{ __('Leave these empty to keep your current password.') }}</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field name="current_password" :label="__('Current password')" type="password" autocomplete="current-password" />
                <x-field name="password" :label="__('New password')" type="password" autocomplete="new-password" />
                <x-field name="password_confirmation" :label="__('Confirm new password')" type="password" autocomplete="new-password" />
            </div>
        </div>

        <button class="btn btn-primary">{{ __('Save profile') }}</button>
    </form>
</x-layouts.app>
