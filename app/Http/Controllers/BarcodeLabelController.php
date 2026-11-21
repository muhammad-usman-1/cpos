<?php

namespace App\Http\Controllers;

use App\Http\Requests\BarcodeLabelRequest;
use App\Models\BarcodeLabel;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Barcode generator: price labels for any product, each with its own scannable code and selling price.
 * Scanning a label at the counter adds the product at the label's price; the product's own price is
 * left as it is.
 */
class BarcodeLabelController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $labels = BarcodeLabel::query()
            ->with('variant.product', 'variant.colour', 'creator')
            ->withSum(['saleItems as sold_qty' => fn ($query) => $query->whereHas('sale', fn ($sale) => $sale->where('status', 'completed'))], 'quantity')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhereHas('variant', fn ($variant) => $variant->where('sku', 'like', '%'.addcslashes($search, '%_\\').'%')
                    ->orWhereHas('product', fn ($product) => $product->search($search)))))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $oldVariantId = old('product_variant_id');
        $oldVariant = $oldVariantId ? ProductVariant::query()->with('product', 'colour')->find($oldVariantId) : null;

        return view('barcode-labels.index', [
            'labels' => $labels,
            'sizes' => LabelController::SIZES,
            'currency' => Setting::get('currency'),
            'shopName' => Setting::get('shop_name'),
            'oldVariant' => $oldVariant,
        ]);
    }

    /**
     * Save the price label and open the print preview with the requested number of copies.
     */
    public function store(BarcodeLabelRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $label = DB::transaction(function () use ($validated, $request): BarcodeLabel {
            $label = BarcodeLabel::create([
                'product_variant_id' => $validated['product_variant_id'],
                'price' => $validated['price'],
                'copies_printed' => $validated['copies'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $label->forceFill(['code' => BarcodeLabel::codeFor($label->id)])->save();

            return $label;
        });

        return redirect()->route('barcode-labels.print', [
            $label,
            'copies' => $validated['copies'],
            'size' => $validated['size'],
            'layout' => $validated['layout'],
        ]);
    }

    /**
     * Print preview: the same label repeated, ready for the label printer or an A4 sheet.
     */
    public function print(Request $request, BarcodeLabel $barcodeLabel): View
    {
        $validated = $request->validate([
            'copies' => ['required', 'integer', 'min:1', 'max:1000'],
            'size' => ['required', Rule::in(array_keys(LabelController::SIZES))],
            'layout' => ['required', Rule::in(['roll', 'sheet'])],
        ]);

        $barcodeLabel->load('variant.product', 'variant.colour');

        return view('barcode-labels.print', [
            'label' => $barcodeLabel,
            'copies' => (int) $validated['copies'],
            'size' => LabelController::SIZES[$validated['size']],
            'layout' => $validated['layout'],
            'currency' => Setting::get('currency'),
            'shopName' => Setting::get('shop_name'),
        ]);
    }

    /**
     * Print more of an existing price label (same code, same price).
     */
    public function reprint(Request $request, BarcodeLabel $barcodeLabel): RedirectResponse
    {
        $validated = $request->validate([
            'copies' => ['required', 'integer', 'min:1', 'max:1000'],
            'size' => ['required', Rule::in(array_keys(LabelController::SIZES))],
            'layout' => ['required', Rule::in(['roll', 'sheet'])],
        ]);

        $barcodeLabel->increment('copies_printed', (int) $validated['copies']);

        return redirect()->route('barcode-labels.print', [$barcodeLabel] + $validated);
    }

    /**
     * Stop or restart a price label. A stopped label no longer scans at the counter.
     */
    public function toggle(BarcodeLabel $barcodeLabel): RedirectResponse
    {
        $barcodeLabel->update(['is_active' => ! $barcodeLabel->is_active]);

        return back()->with('success', $barcodeLabel->is_active
            ? __('Price label :code is active again.', ['code' => $barcodeLabel->code])
            : __('Price label :code is stopped. It will no longer scan at the counter.', ['code' => $barcodeLabel->code]));
    }
}
