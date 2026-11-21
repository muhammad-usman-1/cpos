<x-layouts.app :title="__('Tailors')">
    <p class="mb-4 text-sm text-slate-600">{{ __('Tailors and workshops you give stitching orders to. They do not log in to the system.') }}</p>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('tailors.store') }}" class="card card-body space-y-3 self-start">
            @csrf
            <h2 class="font-semibold">{{ __('Add tailor') }}</h2>
            <x-field name="name" :label="__('Name')" required />
            <x-field name="phone" :label="__('Phone')" inputmode="tel" autocomplete="off" data-phone-check data-ignore-type="user" />
            <button class="btn btn-primary">{{ __('Add') }}</button>
        </form>

        <div class="card overflow-x-auto lg:col-span-2">
            <table class="table">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Orders given') }}</th><th>{{ __('Takes work') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($tailors as $tailor)
                    <tr>
                        <td colspan="5">
                            <form method="POST" action="{{ route('tailors.update', $tailor) }}" class="flex flex-wrap items-start gap-3">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $tailor->name }}" class="input w-44" required>
                                <input name="phone" value="{{ $tailor->phone }}" class="input w-40" inputmode="tel" autocomplete="off" data-phone-check data-ignore-type="user" data-ignore-id="{{ $tailor->id }}">
                                <span class="w-20 pt-2 text-sm text-slate-500">{{ $tailor->tailor_assignments_count }}</span>
                                <label class="inline-flex items-center gap-1 pt-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($tailor->is_active)> {{ __('Active') }}</label>
                                <button class="btn btn-secondary btn-sm mt-1">{{ __('Save') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-500">{{ __('No tailors yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
