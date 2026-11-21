{{-- Shop address, phone, website and tax number from Settings, for printed bills. Empty fields are left out. --}}
@php
    $contactLines = array_filter([
        \App\Models\Setting::get('shop_address'),
        ($phone = \App\Models\Setting::get('shop_phone')) ? __('Ph:').' '.$phone : null,
        \App\Models\Setting::get('shop_website'),
        ($taxNumber = \App\Models\Setting::get('shop_tax_number')) ? __('NTN:').' '.$taxNumber : null,
    ]);
@endphp
@if ($contactLines)
    <div {{ $attributes }}>
        @foreach ($contactLines as $line)
            <div>{{ $line }}</div>
        @endforeach
    </div>
@endif
