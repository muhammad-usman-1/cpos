<x-layouts.app :title="$customer->exists ? __('Edit customer: ').$customer->name : __('Add customer')">
    @php
        $templateOptions = collect($templates)->mapWithKeys(fn ($t) => [$t->value => $t->label()]);
    @endphp

    <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if ($customer->exists)
            @method('PUT')
        @endif
        <input type="hidden" name="return_to" value="{{ request('return_to') }}">

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Left: photo, notes, status. --}}
            <div class="space-y-6">
                <section class="rounded-xl bg-white p-5 text-center shadow-sm ring-1 ring-slate-200" data-photo-field>
                    <div class="mx-auto flex h-32 w-32 items-center justify-center overflow-hidden rounded-full bg-indigo-50 text-indigo-300 ring-4 ring-indigo-100">
                        <img src="{{ $customer->photoUrl() }}" alt="" @class(['h-full w-full object-cover', 'hidden' => ! $customer->photoUrl()]) data-photo-preview>
                        <x-icon name="user" :class="$customer->photoUrl() ? 'hidden h-16 w-16' : 'h-16 w-16'" data-photo-placeholder />
                    </div>
                    <div class="mt-3 font-semibold">{{ __('Customer photo') }}</div>
                    <p class="text-xs text-slate-500">{{ __('Optional. Helps the counter recognise the customer.') }}</p>
                    <input id="photo" type="file" name="photo" accept="image/*" class="sr-only">
                    <label for="photo" class="btn btn-secondary mt-3 cursor-pointer">{{ $customer->photoUrl() ? __('Change photo') : __('Choose photo') }}</label>
                    <p class="mt-2 text-xs text-slate-400">{{ __('JPG, PNG or WebP, up to 4 MB.') }}</p>
                    @error('photo')<p class="error">{{ $message }}</p>@enderror
                    @if ($customer->photoUrl())
                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-rose-600"><input type="checkbox" name="remove_photo" value="1" class="rounded border-slate-300" data-photo-remove> {{ __('Remove photo') }}</label>
                    @endif
                </section>

                <section class="space-y-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <x-field name="notes" :label="__('Notes')" type="textarea" rows="4" :value="$customer->notes" :placeholder="__('e.g. Prefers loose fitting, call before delivery')" />
                    <x-checkbox name="is_active" :label="__('Active customer')" :checked="$customer->is_active ?? true" />
                </section>
            </div>

            {{-- Right: details, measurement form, family. --}}
            <div class="space-y-6 lg:col-span-2">
                <section class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white"><x-icon name="user" class="h-5 w-5" /></span>
                        <h2 class="font-semibold">{{ __('Customer details') }}</h2>
                    </div>
                    <div class="space-y-5 p-5">
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-field name="name" :label="__('Name')" :value="$customer->name" required autofocus class="md:col-span-1" :placeholder="__('e.g. Ahmad Khan')" />
                            <x-field name="phone" :label="__('Phone (used to search)')" :value="$customer->phone" required inputmode="tel" autocomplete="off" placeholder="03XXXXXXXXX"
                                     data-phone-check data-ignore-type="customer" data-ignore-id="{{ $customer->id }}" />
                            <x-field name="city" :label="__('City')" :value="$customer->city" :placeholder="__('e.g. Lahore')" />
                        </div>

                        @messaging
                            <x-select name="preferred_language" :label="__('SMS / WhatsApp language')" :options="\App\Models\MessageTemplate::LANGUAGES" :selected="$customer->preferred_language" :placeholder="__('Shop default')" class="max-w-xs" />
                        @endmessaging

                        @unless ($customer->exists)
                            <div>
                                <div class="label">{{ __('Measurement form for this customer') }} <span class="text-rose-500">*</span></div>
                                <div class="grid max-w-md grid-cols-3 gap-2">
                                    @foreach ($templateOptions as $value => $label)
                                        <label class="flex cursor-pointer items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:border-indigo-400 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-600 has-[:checked]:text-white">
                                            <input type="radio" name="first_profile_template" value="{{ $value }}" class="sr-only" @checked(old('first_profile_template', 'gents') === $value) required>
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('first_profile_template')<p class="error">{{ $message }}</p>@enderror
                            </div>
                        @endunless
                    </div>
                </section>

                @unless ($customer->exists)
                    {{-- Family members added together with the customer (CUS04). --}}
                    @php
                        $relationOptions = collect($relations)->mapWithKeys(fn ($r) => [$r->value => $r->label()]);
                        $relationTemplates = collect($relations)->mapWithKeys(fn ($r) => [$r->value => $r->defaultTemplate()->value]);
                        $familyRows = old('family', []);
                    @endphp
                    <section class="group/family rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white"><x-icon name="users" class="h-5 w-5" /></span>
                                <div>
                                    <h2 class="font-semibold">{{ __('Family members') }}</h2>
                                    <p class="text-xs text-slate-500">{{ __('Optional. Each gets their own measurements.') }}</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" data-add-row="family"><x-icon name="plus" class="me-1 h-4 w-4" />{{ __('Add family member') }}</button>
                        </div>

                        <div class="flex flex-col items-center justify-center gap-1 px-5 py-8 text-center text-sm text-slate-500 group-has-[[data-row]]/family:hidden">
                            <x-icon name="users" class="h-8 w-8 text-slate-300" />
                            <span>{{ __('No family members added. Father, wife, son… can be added here or later on the customer screen.') }}</span>
                        </div>

                        <div class="hidden overflow-x-auto group-has-[[data-row]]/family:block">
                            <table class="table">
                                <thead><tr><th>{{ __('Name') }}</th><th class="w-40">{{ __('Relation') }}</th><th class="w-48">{{ __('Phone') }}</th><th class="w-36">{{ __('Measurement form') }}</th><th class="w-8"></th></tr></thead>
                                <tbody data-rows="family" data-relation-templates="{{ json_encode($relationTemplates) }}">
                                @foreach ($familyRows as $index => $row)
                                    <tr data-row>
                                        <td>
                                            <input name="family[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" class="input" required>
                                            @error("family.$index.name")<p class="error">{{ $message }}</p>@enderror
                                        </td>
                                        <td>
                                            <select name="family[{{ $index }}][relation]" class="input" data-family-relation>
                                                @foreach ($relationOptions as $value => $label)
                                                    <option value="{{ $value }}" @selected(($row['relation'] ?? '') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input name="family[{{ $index }}][phone]" value="{{ $row['phone'] ?? '' }}" class="input" inputmode="tel" autocomplete="off" data-phone-check>
                                            @error("family.$index.phone")<p class="error">{{ $message }}</p>@enderror
                                        </td>
                                        <td>
                                            <select name="family[{{ $index }}][template]" class="input" data-family-template>
                                                @foreach ($templateOptions as $value => $label)
                                                    <option value="{{ $value }}" @selected(($row['template'] ?? '') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><button type="button" class="rounded p-1 text-rose-600 hover:bg-rose-50" data-remove-row title="{{ __('Remove') }}"><x-icon name="x-mark" class="h-5 w-5" /></button></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <template data-row-template="family">
                            <tr data-row>
                                <td><input name="family[__INDEX__][name]" class="input" required placeholder="{{ __('e.g. Rashid') }}"></td>
                                <td>
                                    <select name="family[__INDEX__][relation]" class="input" data-family-relation>
                                        @foreach ($relationOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input name="family[__INDEX__][phone]" class="input" inputmode="tel" autocomplete="off" data-phone-check placeholder="{{ __('Optional') }}"></td>
                                <td>
                                    <select name="family[__INDEX__][template]" class="input" data-family-template>
                                        @foreach ($templateOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><button type="button" class="rounded p-1 text-rose-600 hover:bg-rose-50" data-remove-row title="{{ __('Remove') }}"><x-icon name="x-mark" class="h-5 w-5" /></button></td>
                            </tr>
                        </template>
                    </section>
                @endunless
            </div>
        </div>

        {{-- Save bar stays in view. --}}
        <div class="sticky bottom-0 z-10 flex justify-end gap-2 rounded-xl bg-white/90 px-5 py-3 shadow-[0_-4px_12px_rgba(0,0,0,0.05)] ring-1 ring-slate-200 backdrop-blur">
            <a href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}" class="btn btn-secondary px-6">{{ __('Cancel') }}</a>
            <button class="btn btn-primary px-8">{{ $customer->exists ? __('Save changes') : __('Save customer') }}</button>
        </div>
    </form>
</x-layouts.app>
