<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Barcode label printing in selectable sizes (STK05, PUR07).
 */
class LabelController extends Controller
{
    /**
     * Label sizes in millimetres.
     *
     * @var array<string, array{width: int, height: int, label: string}>
     */
    public const SIZES = [
        '38x25' => ['width' => 38, 'height' => 25, 'label' => '38 x 25 mm (small)'],
        '50x25' => ['width' => 50, 'height' => 25, 'label' => '50 x 25 mm'],
        '50x30' => ['width' => 50, 'height' => 30, 'label' => '50 x 30 mm'],
        '60x40' => ['width' => 60, 'height' => 40, 'label' => '60 x 40 mm (large)'],
    ];

    public function create(Request $request): View
    {
        abort_unless(Setting::usesBarcodes(), 404, __('Barcodes are switched off in Settings.'));

        $variantIds = array_filter((array) $request->query('variants', []), 'is_numeric');
        $lines = collect();

        if ($purchaseId = $request->query('purchase')) {
            $purchase = Purchase::query()->with('items')->findOrFail($purchaseId);
            $lines = $purchase->items->map(fn ($item) => ['variant_id' => $item->product_variant_id, 'copies' => (int) ceil((float) $item->quantity)]);
        } elseif ($variantIds !== []) {
            $lines = collect($variantIds)->map(fn ($id) => ['variant_id' => (int) $id, 'copies' => 1]);
        }

        $variants = ProductVariant::query()->with('product', 'colour')->whereIn('id', $lines->pluck('variant_id'))->get()->keyBy('id');

        return view('labels.create', [
            'lines' => $lines->filter(fn ($line) => $variants->has($line['variant_id']))
                ->map(fn ($line) => $line + ['variant' => $variants[$line['variant_id']]])
                ->values(),
            'sizes' => self::SIZES,
        ]);
    }

    public function print(Request $request): View
    {
        abort_unless(Setting::usesBarcodes(), 404, __('Barcodes are switched off in Settings.'));

        $validated = $request->validate([
            'size' => ['required', Rule::in(array_keys(self::SIZES))],
            'layout' => ['required', Rule::in(['roll', 'sheet'])],
            'show_price' => ['boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer'],
            'items.*.copies' => ['required', 'integer', 'min:0', 'max:500'],
        ]);

        $variants = ProductVariant::query()
            ->with('product', 'colour')
            ->whereIn('id', array_column($validated['items'], 'variant_id'))
            ->get()
            ->keyBy('id');

        $labels = collect($validated['items'])
            ->filter(fn ($item) => $variants->has($item['variant_id']))
            ->flatMap(fn ($item) => array_fill(0, (int) $item['copies'], $variants[$item['variant_id']]));

        return view('labels.print', [
            'labels' => $labels,
            'size' => self::SIZES[$validated['size']],
            'layout' => $validated['layout'],
            'showPrice' => $request->boolean('show_price'),
            'shopName' => Setting::get('shop_name'),
            'currency' => Setting::get('currency'),
        ]);
    }
}
