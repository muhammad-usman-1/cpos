<x-layouts.app :title="__($config['label'])">
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($types as $key => $item)
            <a href="{{ route('masters.index', $key) }}" @class(['btn btn-sm', 'btn-primary' => $key === $type, 'btn-secondary' => $key !== $type])>{{ __($item['label']) }}</a>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('masters.store', $type) }}" class="card card-body space-y-3 self-start">
            @csrf
            <h2 class="font-semibold">{{ __('Add') }} {{ __($config['singular']) }}</h2>
            <x-field name="name" :label="__('Name')" required />
            <button class="btn btn-primary">{{ __('Add') }}</button>
        </form>

        <div class="card overflow-x-auto lg:col-span-2">
            <table class="table">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Active') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td colspan="2">
                            <form method="POST" action="{{ route('masters.update', [$type, $record->id]) }}" class="flex items-center gap-3">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $record->name }}" class="input max-w-xs" required>
                                <label class="inline-flex items-center gap-1 text-sm">
                                    <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300" @checked($record->is_active)> {{ __('Active') }}
                                </label>
                                <button class="btn btn-secondary btn-sm">{{ __('Save') }}</button>
                            </form>
                        </td>
                        <td class="text-end">
                            @can('records.delete')
                                <form method="POST" action="{{ route('masters.destroy', [$type, $record->id]) }}" data-confirm="Remove {{ $record->name }}?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">{{ __('Remove') }}</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-6 text-center text-slate-500">{{ __('Nothing added yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
