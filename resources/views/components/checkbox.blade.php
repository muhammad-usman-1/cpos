@props(['name', 'label', 'checked' => false])

<label {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-sm text-slate-700']) }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600" @checked(old($name, $checked))>
    {{ $label }}
</label>
