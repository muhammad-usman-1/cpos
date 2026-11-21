<x-layouts.app :title="'Measurement fields · '.$template->label()">
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($templates as $item)
            <a href="{{ route('measurement-fields.index', $item) }}" @class(['btn btn-sm', 'btn-primary' => $item === $template, 'btn-secondary' => $item !== $template])>{{ $item->label() }}</a>
        @endforeach
    </div>
    <p class="mb-4 text-sm text-slate-600">{{ __('Rename, reorder or switch off fields to match your naap slip. Switched-off fields disappear from the form but stay in old measurements.') }}</p>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @foreach (['body' => __('Body measurements'), 'style' => __('Style options')] as $group => $heading)
                <div class="card overflow-x-auto">
                    <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold">{{ $heading }}</h2></div>
                    <table class="table">
                        <thead><tr><th class="w-20">{{ __('Order') }}</th><th>{{ __('Label') }}</th><th>{{ __('Type') }}</th><th>{{ __('Options (comma separated)') }}</th><th>{{ __('On') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($fields->get($group, collect()) as $field)
                            <tr>
                                <td colspan="6">
                                    <form method="POST" action="{{ route('measurement-fields.update', $field) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf @method('PUT')
                                        <input type="number" name="sort_order" value="{{ $field->sort_order }}" class="input w-16 py-1">
                                        <input name="label" value="{{ $field->label }}" class="input w-48 py-1" required>
                                        <input type="hidden" name="group" value="{{ $field->group }}">
                                        <select name="type" class="input w-28 py-1">
                                            @foreach (['number' => __('Number'), 'select' => __('Choice'), 'text' => __('Text')] as $value => $label)
                                                <option value="{{ $value }}" @selected($field->type === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input name="options" value="{{ implode(', ', $field->options ?? []) }}" class="input w-64 py-1" placeholder="{{ __('Only for Choice') }}">
                                        <label class="inline-flex items-center gap-1 text-sm"><input type="checkbox" name="is_active" value="1" @checked($field->is_active)> {{ __('On') }}</label>
                                        <button class="btn btn-secondary btn-sm">{{ __('Save') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('measurement-fields.store', $template) }}" class="card card-body space-y-3 self-start">
            @csrf
            <h2 class="font-semibold">{{ __('Add field to') }} {{ $template->label() }}</h2>
            <x-field name="label" :label="__('Label')" required />
            <x-select name="group" :label="__('Section')" :options="['body' => __('Body measurements'), 'style' => __('Style options')]" selected="body" />
            <x-select name="type" :label="__('Type')" :options="['number' => __('Number'), 'select' => __('Choice from list'), 'text' => __('Text')]" selected="number" />
            <x-field name="options" :label="__('Choices (comma separated)')" :hint="__('Only for Choice, e.g. Plain, Embroidered, Lace')" />
            <button class="btn btn-primary">{{ __('Add field') }}</button>
        </form>
    </div>
</x-layouts.app>
