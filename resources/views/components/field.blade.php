@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])

<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }}@if ($required)<span class="text-rose-500"> *</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $attributes->get('rows', 3) }}" class="input" @required($required) {{ $attributes->except(['class', 'rows']) }}>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" class="input" @required($required) {{ $attributes->except('class') }}>
    @endif
    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="error">{{ $message }}</p>
    @enderror
</div>
