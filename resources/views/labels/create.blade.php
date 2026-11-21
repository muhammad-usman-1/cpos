<x-layouts.app :title="__('Print barcode labels')">
    <form method="POST" action="{{ route('labels.print') }}" target="_blank" class="space-y-4">
        @csrf
        <div class="card card-body grid gap-4 sm:grid-cols-3">
            <x-select name="size" :label="__('Label size')" :options="collect($sizes)->map(fn ($s) => $s['label'])" selected="50x25" required />
            <x-select name="layout" :label="__('Printer')" :options="['roll' => __('Label printer (one label per page)'), 'sheet' => __('A4 sticker sheet')]" selected="roll" required />
            <div class="flex items-end"><x-checkbox name="show_price" :label="__('Show sale price')" :checked="true" /></div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                <h2 class="font-semibold">{{ __('Labels') }}</h2>
                <div class="relative w-80" data-variant-picker>
                    <input type="search" class="input" placeholder="{{ __('Add product: search or scan') }}" data-product-search data-url="{{ route('products.lookup') }}" autocomplete="off">
                    <div data-search-results class="absolute end-0 z-10 mt-1 hidden max-h-72 w-full overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg"></div>
                </div>
            </div>
            <table class="table">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('SKU') }}</th><th class="w-32">{{ __('Copies') }}</th><th></th></tr></thead>
                <tbody data-rows="labels">
                @foreach ($lines as $index => $line)
                    <tr data-row>
                        <td>{{ $line['variant']->displayName() }}<input type="hidden" name="items[{{ $index }}][variant_id]" value="{{ $line['variant_id'] }}"></td>
                        <td class="font-mono text-xs">{{ $line['variant']->sku }}</td>
                        <td><input type="number" min="0" max="500" name="items[{{ $index }}][copies]" value="{{ $line['copies'] }}" class="input"></td>
                        <td><button type="button" class="btn btn-secondary btn-sm" data-remove-row>{{ __('Remove') }}</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <template data-row-template="labels">
                <tr data-row>
                    <td><span data-field="label"></span><input type="hidden" name="items[__INDEX__][variant_id]" data-field="variant_id"></td>
                    <td class="font-mono text-xs" data-field="sku"></td>
                    <td><input type="number" min="0" max="500" name="items[__INDEX__][copies]" value="1" class="input"></td>
                    <td><button type="button" class="btn btn-secondary btn-sm" data-remove-row>{{ __('Remove') }}</button></td>
                </tr>
            </template>
        </div>

        <button class="btn btn-primary">{{ __('Print labels') }}</button>
    </form>

    <script>
        // A product picked in the search box becomes a new label row.
        document.addEventListener('variant-chosen', (event) => {
            const template = document.querySelector('template[data-row-template="labels"]');
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(Date.now())).trim();
            const row = wrapper.firstElementChild;
            row.querySelector('[data-field="label"]').textContent = event.detail.label;
            row.querySelector('[data-field="sku"]').textContent = event.detail.sku;
            row.querySelector('[data-field="variant_id"]').value = event.detail.id;
            document.querySelector('[data-rows="labels"]').appendChild(row);
        });
    </script>
</x-layouts.app>
