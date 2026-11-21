{{--
    Measurement inputs for one profile.
    Expects: $fields (grouped body/style), $values (field id => value), $namePrefix (e.g. "values" or "measurements").
--}}
@foreach (['body' => __('Body measurements'), 'style' => __('Style')] as $group => $heading)
    @if ($fields->has($group))
        <h3 class="mb-2 mt-4 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $heading }}</h3>
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($fields[$group] as $field)
                @php($value = old($namePrefix.'.'.$field->id, $values[$field->id] ?? ''))
                <div>
                    <label class="label text-xs" for="{{ $namePrefix }}-{{ $field->id }}">{{ __($field->label) }}</label>
                    @if ($field->type === 'select')
                        <select id="{{ $namePrefix }}-{{ $field->id }}" name="{{ $namePrefix }}[{{ $field->id }}]" class="input">
                            <option value=""></option>
                            @foreach ($field->options ?? [] as $option)
                                <option value="{{ $option }}" @selected($value === $option)>{{ __($option) }}</option>
                            @endforeach
                        </select>
                    @elseif ($field->type === 'number')
                        <input id="{{ $namePrefix }}-{{ $field->id }}" type="number" step="0.25" min="0" max="999" name="{{ $namePrefix }}[{{ $field->id }}]" value="{{ $value }}" class="input text-end" data-numeric-measurement>
                    @else
                        <input id="{{ $namePrefix }}-{{ $field->id }}" type="text" maxlength="100" name="{{ $namePrefix }}[{{ $field->id }}]" value="{{ $value }}" class="input">
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endforeach
