@php
    $extras = old('extras', $order->exists ? $order->extras->map(fn ($e) => ['description' => $e->description, 'amount' => (float) $e->amount])->all() : []);
    $source = old('cloth_source', $order->cloth_source?->value ?? 'customer');
@endphp

<x-layouts.app :title="$order->exists ? __('Edit order ').$order->number : __('New stitching order · ').$customer->name">
    <form method="POST" action="{{ $order->exists ? route('orders.update', $order) : route('orders.store') }}" enctype="multipart/form-data"
          class="space-y-6" data-order-form data-paid="{{ (float) $order->paid_amount }}">
        @csrf
        @if ($order->exists)
            @method('PUT')
        @else
            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
        @endif

        <div class="card card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <span class="label">{{ __('Customer') }}</span>
                <div class="py-2 text-sm font-medium">{{ $customer->name }} · {{ $customer->phone }}</div>
            </div>
            <x-select name="customer_profile_id" :label="__('Stitching for')" :options="$profiles->mapWithKeys(fn ($p) => [$p->id => $p->label().' ('.$p->template->label().')'])" :selected="$selectedProfileId" required />
            <x-select name="type" :label="__('Order type')" :options="collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])" :selected="$order->type" required />
            <div data-parent-order>
                <x-select name="parent_order_id" :label="__('Original order')" :options="$previousOrders->mapWithKeys(fn ($o) => [$o->id => $o->number.' · '.$o->order_date->translatedFormat('d M Y')])" :selected="$order->parent_order_id" :placeholder="__('Choose order')" />
            </div>
        </div>

        {{-- One measurement section per profile; only the selected one is enabled and submitted. --}}
        @foreach ($profiles as $profile)
            @php
                $start = $startingValues[$profile->id];
                $profileVersions = $versions->get($profile->id, collect());
            @endphp
            <div class="card card-body" data-profile-section="{{ $profile->id }}" data-measurement-scope>
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="font-semibold">{{ __('Measurements ·') }} {{ $profile->label() }}</h2>
                        <p class="text-sm text-slate-600">
                            @if ($start)
                                {{ __('Using version') }} {{ $start->version }} {{ __('from') }} {{ $start->created_at->translatedFormat('d M Y') }}{{ __('. Edit any value and a new version is saved with the order.') }}
                            @else
                                {{ __('No saved measurement for') }} {{ $profile->label() }}{{ __('. Enter it now; it is saved to the profile.') }}
                            @endif
                        </p>
                        @if ($profileVersions->count() > 1)
                            <div class="mt-1 flex flex-wrap items-center gap-1 text-xs">
                                <span class="text-slate-500">{{ __('Copy from:') }}</span>
                                @foreach ($profileVersions as $version)
                                    <a href="{{ request()->fullUrlWithQuery(['profile' => $profile->id, 'from' => $version->id]) }}" @class(['rounded px-1.5 py-0.5', 'bg-indigo-600 text-white' => $start?->id === $version->id, 'bg-slate-100 hover:bg-slate-200' => $start?->id !== $version->id])>v{{ $version->version }} · {{ $version->created_at->translatedFormat('d M y') }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="w-36">
                        <label class="label" for="unit-{{ $profile->id }}">{{ __('Unit') }}</label>
                        <select id="unit-{{ $profile->id }}" name="measurement_unit" class="input" data-measurement-unit>
                            <option value="in" @selected(($start?->unit ?? 'in') === 'in')>{{ __('Inches') }}</option>
                            <option value="cm" @selected($start?->unit === 'cm')>{{ __('Centimetres') }}</option>
                        </select>
                    </div>
                </div>

                @include('measurements._form-fields', ['fields' => $fields[$profile->id], 'values' => $start?->valueMap() ?? [], 'namePrefix' => 'measurements'])

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="mnotes-{{ $profile->id }}">{{ __('Measurement instructions') }}</label>
                        <textarea id="mnotes-{{ $profile->id }}" name="measurement_notes" rows="2" class="input">{{ $start?->notes }}</textarea>
                    </div>
                    <div>
                        <label class="label" for="mphoto-{{ $profile->id }}">{{ __('Reference photo') }}</label>
                        <input id="mphoto-{{ $profile->id }}" type="file" name="measurement_photo" accept="image/*" capture="environment" class="block text-sm">
                    </div>
                </div>
            </div>
        @endforeach

        <div class="card card-body space-y-4">
            <h2 class="font-semibold">{{ __('Cloth and design') }}</h2>
            <div class="flex flex-wrap gap-6 text-sm">
                @foreach ($sources as $clothSource)
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="cloth_source" value="{{ $clothSource->value }}" @checked($source === $clothSource->value)> {{ $clothSource->label() }}
                    </label>
                @endforeach
            </div>
            <div data-source-shop>
                <x-select name="sale_id" :label="__('Linked sale')" :options="$recentSales->mapWithKeys(fn ($s) => [$s->id => $s->number.' · '.$s->sale_date->translatedFormat('d M Y').' · '.number_format($s->total, 0)])" :selected="$order->sale_id" :placeholder="__('Choose the sale (or sell the cloth first)')" />
                @if ($recentSales->isEmpty())
                    <p class="mt-1 text-xs text-slate-500">{{ __('This customer has no sales yet. Sell the cloth from New sale, then press "Stitch this cloth" on the receipt screen.') }}</p>
                @endif
            </div>
            <div class="grid gap-4 sm:grid-cols-3" data-source-customer>
                <x-field name="cloth_description" :label="__('Cloth received')" :value="$order->cloth_description" class="sm:col-span-2" :hint="__('Colour, fabric, pieces, any marks.')" />
                <x-field name="cloth_quantity" :label="__('Length received (m)')" type="number" step="0.25" min="0" :value="$order->cloth_quantity ? (float) $order->cloth_quantity : null" />
            </div>
            <div class="grid gap-4 sm:grid-cols-4">
                <x-field name="pieces" :label="__('Number of pieces')" type="number" min="1" max="100" :value="$order->pieces" required />
                <x-field name="promised_date" :label="__('Delivery date')" type="date" :value="$order->promised_date?->toDateString()" required />
                <x-field name="design_notes" :label="__('Design notes')" type="textarea" :value="$order->design_notes" class="sm:col-span-2" />
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="card lg:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h2 class="font-semibold">{{ __('Charges') }}</h2>
                    <button type="button" class="btn btn-secondary btn-sm" data-add-row="extras">{{ __('Add extra (lining, embroidery…)') }}</button>
                </div>
                <div class="card-body space-y-3">
                    <x-field name="stitching_charges" :label="__('Stitching charges')" type="number" step="1" min="0" :value="$order->exists ? (float) $order->stitching_charges : null" required class="max-w-xs" />
                    <table class="table">
                        <tbody data-rows="extras">
                        @foreach ($extras as $index => $extra)
                            <tr data-row>
                                <td><input name="extras[{{ $index }}][description]" value="{{ $extra['description'] ?? '' }}" class="input" placeholder="{{ __('e.g. Lining') }}"></td>
                                <td class="w-40"><input type="number" step="1" min="0" name="extras[{{ $index }}][amount]" value="{{ $extra['amount'] ?? '' }}" class="input text-end" data-field="amount"></td>
                                <td class="w-8"><button type="button" class="text-rose-600" data-remove-row>✕</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <template data-row-template="extras">
                        <tr data-row>
                            <td><input name="extras[__INDEX__][description]" class="input" placeholder="{{ __('e.g. Lining') }}"></td>
                            <td class="w-40"><input type="number" step="1" min="0" name="extras[__INDEX__][amount]" class="input text-end" data-field="amount"></td>
                            <td class="w-8"><button type="button" class="text-rose-600" data-remove-row>✕</button></td>
                        </tr>
                    </template>
                </div>
            </div>

            <div class="card card-body space-y-3 text-sm">
                <div class="flex justify-between text-lg font-semibold"><span>{{ __('Total') }}</span><span data-order-total>0.00</span></div>
                @unless ($order->exists)
                    <x-field name="advance_amount" :label="__('Advance received')" type="number" step="1" min="0" />
                    <x-select name="advance_method" :label="__('Advance paid by')" :options="collect($methods)->reject(fn ($m) => $m->value === 'cheque')->mapWithKeys(fn ($m) => [$m->value => $m->label()])" selected="cash" />
                @else
                    <div class="flex justify-between"><span>{{ __('Already paid') }}</span><span>{{ number_format($order->paid_amount, 2) }}</span></div>
                @endunless
                <div class="flex justify-between font-medium"><span>{{ __('Balance on delivery') }}</span><span data-order-balance>0.00</span></div>
                <button class="btn btn-primary w-full py-3">{{ $order->exists ? __('Save changes') : __('Create order') }}</button>
                <a href="{{ $order->exists ? route('orders.show', $order) : route('customers.show', $customer) }}" class="btn btn-secondary w-full">{{ __('Cancel') }}</a>
            </div>
        </div>
    </form>
</x-layouts.app>
