<x-layouts.app :title="__('Settings')">
    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6" enctype="multipart/form-data">
        @csrf @method('PUT')

        {{-- Shop details: printed at the top of every bill. --}}
        <section class="card card-body space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-indigo-700">{{ __('Shop details') }}</h2>
                <p class="text-sm text-slate-500">{{ __('Printed at the top of receipts, slips, vouchers and statements. Leave a field empty to leave it off the bill.') }}</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <x-field name="shop_name" :label="__('Shop name')" :value="$settings['shop_name']" required />
                <x-field name="shop_phone" :label="__('Shop phone number')" :value="$settings['shop_phone']" inputmode="tel"
                         :hint="__('You can enter two numbers, e.g. 0300-1234567, 042-1234567.')" />
                <x-field name="shop_address" :label="__('Shop address')" :value="$settings['shop_address']" :placeholder="__('e.g. Shop 12, Main Bazaar, Lahore')" />
                <x-field name="shop_website" :label="__('Website / social media')" :value="$settings['shop_website']" :placeholder="__('e.g. instagram.com/yourshop')" />
                <x-field name="shop_tax_number" :label="__('Tax / NTN number')" :value="$settings['shop_tax_number']" :hint="__('Only if you want it printed on bills.')" />
            </div>

            {{-- Two logos: one for the screens (top bar, login), one for printed bills. --}}
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ([
                    'header_logo' => [__('Header logo'), __('Shown in the top bar and on the login pages. A wide logo on a clear or white background works best.'), \App\Models\Setting::headerLogoUrl(), 'bg-indigo-600'],
                    'bill_logo' => [__('Bill logo'), __('Printed at the top of receipts, order and measurement slips, purchase vouchers, returns and statements. Black and white prints best on thermal printers.'), \App\Models\Setting::billLogoUrl(), 'bg-white'],
                ] as $key => [$label, $hint, $url, $background])
                    <div class="flex flex-wrap items-center gap-4 rounded-md border border-slate-200 p-4" data-photo-field>
                        <div class="flex h-20 w-36 shrink-0 items-center justify-center overflow-hidden rounded-lg {{ $background }} p-2 ring-1 ring-slate-200">
                            <img src="{{ $url }}" alt="" @class(['max-h-full max-w-full object-contain', 'hidden' => ! $url]) data-photo-preview>
                            <x-icon name="squares" :class="$url ? 'hidden h-8 w-8 text-slate-300' : 'h-8 w-8 text-slate-300'" data-photo-placeholder />
                        </div>
                        <div class="min-w-0 flex-1 space-y-1">
                            <label class="label" for="{{ $key }}">{{ $label }} <span class="font-normal text-slate-400">({{ __('optional') }})</span></label>
                            <input id="{{ $key }}" type="file" name="{{ $key }}" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-slate-600 file:me-3 file:rounded-md file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-indigo-700">
                            <p class="text-xs text-slate-500">{{ $hint }} {{ __('PNG, JPG or WebP, up to 2 MB.') }}</p>
                            @error($key)<p class="error">{{ $message }}</p>@enderror
                            @if ($url)
                                <label class="inline-flex items-center gap-2 text-sm text-rose-600"><input type="checkbox" name="remove_{{ $key }}" value="1" class="rounded border-slate-300" data-photo-remove> {{ __('Remove logo') }}</label>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Sale receipt content. --}}
        <section class="card card-body space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-indigo-700">{{ __('Sale receipt') }}</h2>
                <p class="text-sm text-slate-500">{{ __('What is printed on the customer\'s bill, besides the items and totals.') }}</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <x-field name="receipt_header_note" :label="__('Message at the top')" :value="$settings['receipt_header_note']" :placeholder="__('e.g. Eid collection now in stock!')"
                         :hint="__('A short line under the shop details.')" />
                <x-field name="receipt_footer" :label="__('Message at the bottom')" :value="$settings['receipt_footer']" :hint="__('e.g. Thank you for shopping with us.')" />
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <x-field name="receipt_policies" :label="__('Exchange and return policy')" type="textarea" rows="5" :value="$settings['receipt_policies']"
                             :hint="__('One rule per line. Each line prints as a point on the receipt. Leave empty to print no policy.')" />
                </div>
                <div class="space-y-3">
                    <x-field name="exchange_days" :label="__('Exchange allowed for (days)')" type="number" min="0" max="365" :value="$settings['exchange_days']"
                             :hint="__('Prints “Exchange valid till” with the date. 0 leaves it off.')" />
                    <x-checkbox name="receipt_show_savings" :label="__('Show “You saved” (discounts given)')" :checked="$settings['receipt_show_savings'] === '1'" />
                    <x-checkbox name="receipt_show_item_count" :label="__('Show number of items and pieces')" :checked="$settings['receipt_show_item_count'] === '1'" />
                    <x-checkbox name="receipt_show_balance" :label="__('Show the customer\'s total balance due')" :checked="$settings['receipt_show_balance'] === '1'" />
                </div>
            </div>
        </section>

        {{-- Stitching order slip content. --}}
        <section class="card card-body space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-indigo-700">{{ __('Stitching order slip') }}</h2>
                <p class="text-sm text-slate-500">{{ __('The customer copy given when a stitching order is booked.') }}</p>
            </div>
            <x-field name="order_slip_terms" :label="__('Terms and conditions')" type="textarea" rows="5" :value="$settings['order_slip_terms']"
                     :hint="__('One rule per line. Each line prints as a point on the slip. Leave empty to print none.')" />
        </section>

        {{-- System. --}}
        <section class="card card-body space-y-4">
            <h2 class="text-lg font-semibold text-indigo-700">{{ __('System') }}</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <x-field name="currency" :label="__('Currency')" :value="$settings['currency']" required />
                <x-field name="country_code" :label="__('Country dialling code')" :value="$settings['country_code']" required :hint="__('e.g. 92 for Pakistan.')" />
                <x-field name="idle_timeout_minutes" :label="__('Auto logout after inactivity (minutes)')" type="number" min="0" max="720" :value="$settings['idle_timeout_minutes']" required :hint="__('0 turns auto logout off.')" />
            </div>
            <x-checkbox name="use_barcodes" :label="__('Use barcodes (label printing and barcodes on receipts and slips)')" :checked="$settings['use_barcodes'] === '1'" />
            <x-checkbox name="use_messaging" :label="__('Use SMS / WhatsApp messages (order ready, payment received, reminders, message log)')" :checked="$settings['use_messaging'] === '1'" />
            <x-checkbox name="allow_negative_stock" :label="__('Allow stock to go below zero')" :checked="$settings['allow_negative_stock'] === '1'" />
            <p class="text-xs text-slate-500">{{ __('When off, the system blocks any stock-out (returns, cancellations, adjustments, and later sales) that would take a product below zero.') }}</p>
        </section>

        <div class="sticky bottom-0 z-10 -mx-1 flex justify-end rounded-lg bg-slate-100/90 px-1 py-3 backdrop-blur">
            <button class="btn btn-primary px-8 py-2.5 text-base">{{ __('Save settings') }}</button>
        </div>
    </form>
</x-layouts.app>
