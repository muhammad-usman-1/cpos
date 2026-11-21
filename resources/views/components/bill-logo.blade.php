{{-- The shop's bill logo from Settings, for printed bills. Shows nothing when no logo is uploaded. --}}
@php
    $billLogoUrl = \App\Models\Setting::billLogoUrl();
@endphp
@if ($billLogoUrl)
    <img src="{{ $billLogoUrl }}" alt="{{ \App\Models\Setting::get('shop_name') }}" {{ $attributes->merge(['style' => 'display: block; max-height: 18mm; max-width: 60mm; object-fit: contain;']) }}>
@endif
