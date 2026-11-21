<x-layouts.app :title="'Measurement v'.$measurement->version.' · '.$measurement->profile->customer->name.' · '.$measurement->profile->label()">
    <x-slot:actions>
        <a href="{{ route('measurements.print', $measurement) }}" target="_blank" class="btn btn-secondary">{{ __('Print slip') }}</a>
        @can('measurements.manage')
            <a href="{{ route('measurements.create', [$measurement->profile, 'from' => $measurement->id]) }}" class="btn btn-primary">{{ __('Copy into new version') }}</a>
        @endcan
        <a href="{{ route('customers.show', $measurement->profile->customer_id) }}" class="btn btn-secondary">{{ __('Back to customer') }}</a>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-4">
        <div class="card card-body lg:col-span-3">
            <p class="mb-4 text-sm text-slate-600">
                {{ __('Taken') }} {{ $measurement->created_at->translatedFormat('d M Y H:i') }} {{ __('by') }} {{ $measurement->takenBy?->name ?? '—' }} · {{ $measurement->unitLabel() }}
                @if ($measurement->is_current)<span class="badge badge-green">{{ __('Current') }}</span>@else<span class="badge badge-gray">{{ __('Old version') }}</span>@endif
            </p>
            @foreach (['body' => __('Body measurements'), 'style' => __('Style')] as $group => $heading)
                @php($groupValues = $measurement->values->filter(fn ($value) => $value->field?->group === $group)->sortBy(fn ($value) => $value->field->sort_order))
                @if ($groupValues->isNotEmpty())
                    <h3 class="mb-2 mt-2 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $heading }}</h3>
                    <dl class="mb-4 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-3">
                        @foreach ($groupValues as $value)
                            <div class="flex justify-between border-b border-dashed border-slate-100"><dt class="text-slate-500">{{ __($value->field->label) }}</dt><dd class="font-medium">{{ __($value->value) }}</dd></div>
                        @endforeach
                    </dl>
                @endif
            @endforeach
            @if ($measurement->notes)
                <p class="text-sm"><span class="font-medium">{{ __('Instructions:') }}</span> {{ $measurement->notes }}</p>
            @endif
            @if ($measurement->photoUrl())
                <img src="{{ $measurement->photoUrl() }}" alt="" class="mt-4 max-h-80 rounded-md border">
            @endif
        </div>

        <div class="card">
            <div class="border-b border-slate-200 px-4 py-3"><h2 class="font-semibold">{{ __('History') }}</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($measurement->profile->measurements as $version)
                    <li @class(['px-4 py-2', 'bg-indigo-50' => $version->id === $measurement->id])>
                        <a href="{{ route('measurements.show', $version) }}" class="font-medium text-indigo-600 hover:underline">{{ __('Version') }} {{ $version->version }}</a>
                        @if ($version->is_current)<span class="badge badge-green">{{ __('Current') }}</span>@endif
                        <div class="text-xs text-slate-500">{{ $version->created_at->translatedFormat('d M Y') }} · {{ $version->takenBy?->name }}</div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-layouts.app>
