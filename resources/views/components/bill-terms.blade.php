@props(['setting', 'title'])

{{-- Policies or terms from a multi-line setting, printed as numbered points. Nothing when the setting is empty. --}}
@php
    $termLines = \App\Models\Setting::lines($setting);
@endphp
@if ($termLines)
    <div {{ $attributes }}>
        <div style="font-weight: bold; margin-bottom: 2px;">{{ $title }}</div>
        <ol style="margin: 0; padding-inline-start: 1.3em;">
            @foreach ($termLines as $line)
                <li style="margin-bottom: 1px;">{{ $line }}</li>
            @endforeach
        </ol>
    </div>
@endif
