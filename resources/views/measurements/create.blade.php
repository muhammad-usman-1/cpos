<x-layouts.app :title="__('Edit').' · '.$profile->customer->name.' · '.$profile->label()">
    <form method="POST" action="{{ route('measurements.store', $profile) }}" enctype="multipart/form-data" class="space-y-6" data-measurement-scope>
        @csrf

        {{-- Who this is: name, relation, phone and form type, saved together with the measurements. --}}
        @can('customers.manage')
            @php
                $isSelf = $profile->relation === \App\Enums\FamilyRelation::Self;
            @endphp
            <div class="card card-body">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold">{{ $isSelf ? __('Customer') : __('Family member') }}</h2>
                    <a href="{{ route('customers.show', $profile->customer_id) }}" class="text-sm text-slate-500 hover:underline">{{ $profile->customer->name }}</a>
                </div>
                <div @class(['grid gap-4', 'sm:grid-cols-2' => $isSelf, 'sm:grid-cols-2 lg:grid-cols-4' => ! $isSelf])>
                    <div>
                        <label class="label" for="profile-name">{{ __('Name') }} <span class="text-rose-500">*</span></label>
                        <input id="profile-name" name="profile[name]" value="{{ old('profile.name', $profile->name) }}" class="input" required maxlength="80">
                        @error('name')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    @unless ($isSelf)
                        <div>
                            <label class="label" for="profile-relation">{{ __('Relation') }}</label>
                            <select id="profile-relation" name="profile[relation]" class="input">
                                @foreach ($relations as $relation)
                                    <option value="{{ $relation->value }}" @selected(old('profile.relation', $profile->relation?->value) === $relation->value)>{{ $relation->label() }}</option>
                                @endforeach
                            </select>
                            @error('relation')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label" for="profile-phone">{{ __('Phone') }} <span class="font-normal text-slate-400">({{ __('optional') }})</span></label>
                            <input id="profile-phone" name="profile[phone]" value="{{ old('profile.phone', $profile->phone) }}" class="input" inputmode="tel" autocomplete="off"
                                   data-phone-check data-ignore-type="profile" data-ignore-id="{{ $profile->id }}">
                            @error('phone')<p class="error">{{ $message }}</p>@enderror
                        </div>
                    @endunless
                    <div>
                        <label class="label" for="profile-template">{{ __('Measurement form') }}</label>
                        <select id="profile-template" name="profile[template]" class="input">
                            @foreach ($templates as $template)
                                <option value="{{ $template->value }}" @selected(old('profile.template', $profile->template->value) === $template->value)>{{ $template->label() }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Changing it saves the details and opens the new form.') }}</p>
                    </div>
                </div>
            </div>
        @endcan

        <div class="card card-body">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="text-sm text-slate-600">
                    {{ $profile->template->label() }} {{ __('form.') }}
                    @if ($source)
                        {{ __('Starting from') }} <strong>{{ __('version') }} {{ $source->version }}</strong> ({{ $source->created_at->translatedFormat('d M Y') }}{{ __('). Change only what is different; saving creates a new version and keeps the old one.') }}
                    @else
                        {{ __('First measurement for this profile.') }}
                    @endif
                </div>
                <div class="w-40">
                    <label class="label" for="unit">{{ __('Unit') }}</label>
                    <select id="unit" name="unit" class="input" data-measurement-unit>
                        <option value="in" @selected(old('unit', $source?->unit ?? 'in') === 'in')>{{ __('Inches') }}</option>
                        <option value="cm" @selected(old('unit', $source?->unit) === 'cm')>{{ __('Centimetres') }}</option>
                    </select>
                </div>
            </div>

            @include('measurements._form-fields', ['fields' => $fields, 'values' => $source?->valueMap() ?? [], 'namePrefix' => 'values'])
        </div>

        <div class="card card-body grid gap-4 sm:grid-cols-2">
            <x-field name="notes" :label="__('Special instructions')" type="textarea" :value="$source?->notes" :hint="__('For example: keep shoulder slightly loose, left arm 0.5 longer.')" />
            <div>
                <label class="label" for="photo">{{ __('Reference photo') }}</label>
                <input id="photo" type="file" name="photo" accept="image/*" capture="environment" class="block text-sm">
                @if ($source?->photoUrl())
                    <div class="mt-2 flex items-center gap-3">
                        <img src="{{ $source->photoUrl() }}" alt="" class="h-20 w-20 rounded border object-cover">
                        <x-checkbox name="remove_photo" :label="__('Remove this photo')" />
                    </div>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('customers.show', $profile->customer_id) }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            @if ($profile->measurements->count() > 1)
                <span class="ms-4 text-sm text-slate-500">{{ __('Start from an older version:') }}</span>
                @foreach ($profile->measurements as $version)
                    <a href="{{ route('measurements.create', [$profile, 'from' => $version->id]) }}" @class(['btn btn-sm', 'btn-primary' => $source?->id === $version->id, 'btn-secondary' => $source?->id !== $version->id])>v{{ $version->version }}</a>
                @endforeach
            @endif
        </div>
    </form>
</x-layouts.app>
