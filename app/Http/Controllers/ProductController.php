<?php

namespace App\Http\Controllers;

use App\Enums\SaleStatus;
use App\Http\Requests\ProductRequest;
use App\Models\BarcodeLabel;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,low'],
        ]);

        $products = Product::query()
            ->with('variants.colour')
            ->withSum('variants as total_stock', 'stock_qty')
            ->search($filters['q'] ?? null)
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when(($filters['status'] ?? null) === 'low', fn ($q) => $q->whereHas('variants', fn ($v) => $v->lowStock()))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('products.index', ['products' => $products]);
    }

    public function create(): View
    {
        return view('products.form', ['product' => new Product(['is_active' => true]), 'variant' => null]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->products->save(new Product, $this->formData($request));

        // Back to an empty form, with a box asking to add another product or go back to the list.
        return redirect()->route('products.create')->with('product_created', [
            'name' => $product->name,
            'sku' => $product->variants()->value('sku'),
        ]);
    }

    public function show(Product $product): View
    {
        $product->load('creator', 'variants.colour');

        return view('products.show', ['product' => $product]);
    }

    public function edit(Product $product): View
    {
        $product->load('variants.colour');

        return view('products.form', [
            'product' => $product,
            'variant' => $product->variants->count() === 1 ? $product->variants->first() : null,
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->products->save($product, $this->formData($request));

        return redirect()->route('products.show', $product)->with('success', __('Product saved.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(ProductRequest $request): array
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        if (! $request->user()->can('cost.view')) {
            unset($data['cost_price']);
        }

        return $data;
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ((float) $product->variants()->sum('stock_qty') != 0.0) {
            return back()->with('error', __('This product still has stock. Set its quantity to zero or mark it inactive instead.'));
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', __('Product deleted. It stays in history and reports.'));
    }

    /**
     * Variant lookup for the purchase and adjustment screens. An exact barcode match comes first.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if ($term === '') {
            return response()->json([]);
        }

        $canSeeCost = $request->user()->can('cost.view');

        // On the counter screen, a scanned bill barcode (or typed invoice number) finds that sale.
        if ($request->boolean('with_sales') && $request->user()->can('sales.view')) {
            $sale = Sale::query()
                ->with('customer')
                ->where('number', mb_strtoupper($term))
                ->where('status', SaleStatus::Completed)
                ->first();

            if ($sale) {
                return response()->json([[
                    'type' => 'sale',
                    'id' => $sale->id,
                    'number' => $sale->number,
                    'label' => __('Bill :number', ['number' => $sale->number]),
                    'details' => collect([
                        $sale->completed_at?->translatedFormat('d M Y h:i A'),
                        $sale->customer?->name ?? __('Walk-in'),
                    ])->filter()->implode(' · '),
                    'total' => (float) $sale->total,
                    'url' => route('sales.show', $sale),
                ]]);
            }
        }

        // A scanned price label: the product at the label's own price, not its normal price.
        $priceLabel = BarcodeLabel::query()
            ->active()
            ->with('variant.product', 'variant.colour')
            ->where('code', $term)
            ->whereHas('variant', fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))
            ->first();

        if ($priceLabel) {
            $variant = $priceLabel->variant;

            return response()->json([[
                'id' => $variant->id,
                'sku' => $priceLabel->code,
                'label' => $variant->displayName(),
                'code' => $variant->product->code,
                'unit' => $variant->product->unit->value,
                'fractional' => $variant->product->allowsFractionalQuantity(),
                'stock' => (float) $variant->stock_qty,
                'cost' => $canSeeCost ? (float) $variant->product->cost_price : null,
                'sale_price' => (float) $priceLabel->price,
                'normal_price' => (float) $variant->product->sale_price,
                'min_price' => (float) $variant->product->min_sale_price,
                'barcode_label_id' => $priceLabel->id,
            ]]);
        }

        $exact = ProductVariant::query()->with('product', 'colour')->where('is_active', true)->where('sku', $term)->get();

        $variants = $exact->isNotEmpty() ? $exact : ProductVariant::query()
            ->with('product', 'colour')
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->where(fn ($q) => $q->where('sku', 'like', addcslashes($term, '%_\\').'%')
                ->orWhereHas('product', fn ($q) => $q->search($term)))
            ->limit(20)
            ->get();

        $variants = $variants->map(fn (ProductVariant $variant): array => [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'label' => $variant->displayName(),
            'code' => $variant->product->code,
            'unit' => $variant->product->unit->value,
            'fractional' => $variant->product->allowsFractionalQuantity(),
            'stock' => (float) $variant->stock_qty,
            'cost' => $canSeeCost ? (float) $variant->product->cost_price : null,
            'sale_price' => (float) $variant->product->sale_price,
            'min_price' => (float) $variant->product->min_sale_price,
        ]);

        return response()->json($variants);
    }
}
