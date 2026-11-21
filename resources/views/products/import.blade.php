<x-layouts.app :title="__('Import products')">
    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="card card-body space-y-4 self-start">
            @csrf
            <p class="text-sm text-slate-600">{{ __('Upload a CSV file to load products and opening stock. In Excel, use') }} <em>{{ __('File → Save As → CSV UTF-8') }}</em>.</p>
            <div>
                <label class="label" for="file">{{ __('CSV file') }}</label>
                <input id="file" type="file" name="file" accept=".csv,text/csv" required class="block text-sm">
                @error('file')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="flex gap-2">
                <button class="btn btn-primary">{{ __('Import') }}</button>
                <a href="{{ route('products.import.template') }}" class="btn btn-secondary">{{ __('Download template') }}</a>
            </div>
        </form>

        <div class="card card-body text-sm">
            <h2 class="mb-2 font-semibold">{{ __('How the file is read') }}</h2>
            <ul class="list-disc space-y-1 ps-5 text-slate-700">
                <li>{{ __('Columns:') }} <span class="font-mono text-xs" dir="ltr">{{ implode(', ', $columns) }}</span></li>
                <li>{{ __('One row is one product. Only the name is required.') }}</li>
                <li>{{ __('Leave barcode empty to have a code made automatically. A barcode that already exists is not imported again.') }}</li>
                <li>{{ __('Quantity is the stock you have now; it is saved as opening stock.') }}</li>
                <li>{{ __('Rows with errors are skipped and listed below; other rows are still imported.') }}</li>
            </ul>
        </div>
    </div>

    @if (session('import_result') && session('import_result')['errors'])
        <div class="card card-body mt-6">
            <h2 class="mb-2 font-semibold text-rose-700">{{ __('Rows not imported') }}</h2>
            <ul class="list-disc ps-5 text-sm text-rose-700">
                @foreach (session('import_result')['errors'] as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</x-layouts.app>
