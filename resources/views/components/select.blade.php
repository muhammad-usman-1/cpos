@props(['name', 'label', 'options' => [], 'selected' => null, 'placeholder' => null, 'required' => false])

{{-- $options is a list of value => label pairs. --}}
@php
    $current = (string) old($name, $selected instanceof \BackedEnum ? $selected->value : $selected);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }}@if ($required)<span class="text-rose-500"> *</span>@endif</label>
    <select id="{{ $name }}" name="{{ $name }}" class="input" @required($required) {{ $attributes->except('class') }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}" @selected($current === (string) $value)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($name)
        <p class="error">{{ $message }}</p>
    @enderror
</div>
