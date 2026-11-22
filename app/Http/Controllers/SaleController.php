<?php

namespace App\Http\Controllers;

use App\Enums\FamilyRelation;
use App\Enums\MeasurementTemplate;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Http\Requests\SaleRequest;
use App\Models\BarcodeLabel;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function __construct(private SaleService $sales) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer'],
            'credit' => ['nullable', 'boolean'],
        ]);

        $query = Sale::query()
            ->with('customer', 'creator')
            ->where('status', SaleStatus::Completed)
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('number', 'like', "%$term%")
                ->orWhereHas('customer', fn ($c) => $c->search($term))))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('sale_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('sale_date', '<=', $to))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('created_by', $id))
            ->when($filters['credit'] ?? false, fn ($q) => $q->where('credit_amount', '>', 0));

        $totals = (clone $query)->selectRaw('COALESCE(SUM(total), 0) as total, COALESCE(SUM(paid_amount), 0) as paid, COALESCE(SUM(credit_amount), 0) as credit, COUNT(*) as bills')->toBase()->first();

        return view('sales.index', [
            'sales' => $query->latest('id')->paginate(25)->withQueryString(),
            'totals' => $totals,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * The counter screen (SAL01 to SAL03, SAL08). Pass ?resume={held sale id} to reopen a held bill.
     */
    public function create(Request $request): Response
    {
        $held = null;
        $lines = collect();

        if ($request->query('resume')) {
            $held = Sale::query()->with('customer', 'items')->where('status', SaleStatus::Held)->findOrFail($request->query('resume'));
            $lines = $held->items->map(fn ($item): array => $item->only(['product_variant_id', 'barcode_label_id', 'quantity', 'price', 'discount']));
        }

        // After a failed save, rebuild the bill from what was submitted.
        if (is_array($request->old('items'))) {
            $lines = collect($request->old('items'));
        }

        $variants = ProductVariant::query()->with('product', 'colour')->whereIn('id', $lines->pluck('product_variant_id'))->get()->keyBy('id');
        $priceLabels = BarcodeLabel::query()->whereIn('id', $lines->pluck('barcode_label_id')->filter())->get()->keyBy('id');
        $items = $lines->filter(fn (array $line) => $variants->has($line['product_variant_id'] ?? null))->map(function (array $line) use ($variants, $priceLabels): array {
            $variant = $variants[$line['product_variant_id']];
            $priceLabel = $priceLabels->get($line['barcode_label_id'] ?? null);

            return [
                'product_variant_id' => $variant->id,
                'barcode_label_id' => $priceLabel?->id,
                'label' => $variant->displayName(),
                'sku' => $priceLabel?->code ?? $variant->sku,
                'fractional' => $variant->product->allowsFractionalQuantity(),
                'stock' => (float) $variant->stock_qty,
                'quantity' => (float) ($line['quantity'] ?? 1),
                'price' => (float) ($line['price'] ?? $variant->product->sale_price),
                'discount_percent' => $this->discountPercent($line),
                'min_price' => (float) $variant->product->min_sale_price,
            ];
        })->values()->all();

        $customerId = $request->old('customer_id', $held?->customer_id ?? $request->query('customer'));
        $customer = $customerId ? Customer::query()->find($customerId) : null;

        $response = response()->view('sales.create', [
            'held' => $held,
            'heldBillDiscountPercent' => $held && (float) $held->bill_discount > 0 && (float) $held->subtotal - (float) $held->item_discount > 0
                ? round((float) $held->bill_discount / ((float) $held->subtotal - (float) $held->item_discount) * 100, 2)
                : '',
            'items' => $items,
            'customer' => $customer,
            'methods' => PaymentMethod::cases(),
            'heldSales' => Sale::query()->with('customer', 'creator')->where('status', SaleStatus::Held)->latest('id')->limit(20)->get(),
        ]);

        // Only an empty new bill (no messages, no held bill, no typed data) is kept for offline use.
        $isBlankBill = ! $held && ! $customer && ! $request->session()->hasAny(['completed_sale', 'success', 'error', 'errors', '_old_input']);

        return $isBlankBill ? $response->header('X-Offline-Copy', '1') : $response;
    }

    public function store(SaleRequest $request): RedirectResponse|JsonResponse
    {
        // An offline bill uploaded again (e.g. the connection dropped after saving) returns the sale already made.
        $alreadyUploaded = $request->validated('client_uuid')
            ? Sale::query()->where('client_uuid', $request->validated('client_uuid'))->first()
            : null;

        if ($alreadyUploaded) {
            return $this->offlineUploadResponse($alreadyUploaded);
        }

        $held = $request->integer('held_id') ? Sale::query()->findOrFail($request->integer('held_id')) : null;
        $isHold = $request->validated('action') === 'hold';

        // The customer typed on the counter is created together with the bill, so a failed bill leaves no stray customer.
        $sale = DB::transaction(function () use ($request, $held, $isHold): Sale {
            $data = $request->saleData();
            $data['customer_id'] = $data['customer_id'] ?? $this->counterCustomer($request)?->id;

            return $isHold ? $this->sales->hold($data, $held) : $this->sales->complete($data, $held);
        });

        if ($request->validated('client_uuid') && $request->expectsJson()) {
            return $this->offlineUploadResponse($sale);
        }

        if ($isHold) {
            return redirect()->route('sales.create')->with('success', __('Bill held as :number. Resume it from the held bills list.', ['number' => $sale->number]));
        }

        return redirect()->route('sales.create')->with('completed_sale', [
            'number' => $sale->number,
            'total' => (float) $sale->total,
            'show_url' => route('sales.show', $sale),
            'receipt_url' => route('sales.receipt', [$sale, 'format' => '80mm']),
        ]);
    }

    /**
     * Reply to the sale screen when it uploads a bill made offline.
     */
    private function offlineUploadResponse(Sale $sale): JsonResponse
    {
        return response()->json([
            'id' => $sale->id,
            'number' => $sale->number,
            'total' => (float) $sale->total,
            'show_url' => route('sales.show', $sale),
        ]);
    }

    /**
     * Products for the sale screen to search while the internet is down, in the same shape as the live search.
     */
    public function offlineCatalogue(Request $request): JsonResponse
    {
        $canSeeCost = $request->user()->can('cost.view');

        $variants = ProductVariant::query()
            ->with('product', 'colour')
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->get()
            ->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'label' => $variant->displayName(),
                'code' => $variant->product->code,
                'unit' => $variant->product->unit->value,
                'fractional' => $variant->product->allowsFractionalQuantity(),
                'stock' => (float) $variant->stock_qty,
                'cost' => $canSeeCost ? (float) $variant->product->cost_price : null,
                'sale_price' => (float) $variant->product->sale_price,
                'normal_price' => (float) $variant->product->sale_price,
                'min_price' => (float) $variant->product->min_sale_price,
                'barcode_label_id' => null,
            ]);

        $labels = BarcodeLabel::query()
            ->active()
            ->with('variant.product', 'variant.colour')
            ->whereHas('variant', fn ($query) => $query->whereNull('deleted_at')->where('is_active', true))
            ->get()
            ->map(fn (BarcodeLabel $label) => [
                'id' => $label->variant->id,
                'sku' => $label->code,
                'label' => $label->variant->displayName(),
                'code' => $label->variant->product->code,
                'unit' => $label->variant->product->unit->value,
                'fractional' => $label->variant->product->allowsFractionalQuantity(),
                'stock' => (float) $label->variant->stock_qty,
                'cost' => $canSeeCost ? (float) $label->variant->product->cost_price : null,
                'sale_price' => (float) $label->price,
                'normal_price' => (float) $label->variant->product->sale_price,
                'min_price' => (float) $label->variant->product->min_sale_price,
                'barcode_label_id' => $label->id,
            ]);

        return response()->json([
            'saved_at' => now()->toIso8601String(),
            'products' => $variants->values(),
            'labels' => $labels->values(),
        ]);
    }

    /**
     * A fresh security token so bills saved offline can be uploaded once the internet is back.
     */
    public function offlineToken(): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }

    /**
     * The customer for the phone typed in the counter's customer card: the existing one, or a new one
     * made from the phone and name (with a "Self" measurement profile, like the Add customer form).
     */
    private function counterCustomer(SaleRequest $request): ?Customer
    {
        $phone = $request->validated('new_customer_phone');

        if (! $phone) {
            return null;
        }

        $existing = $request->customerForPhone();

        if ($existing) {
            return $existing;
        }

        $customer = Customer::create([
            'name' => $request->validated('new_customer_name'),
            'phone' => $phone,
            'discount_percent' => 0,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);
        $customer->profiles()->create(['name' => 'Self', 'relation' => FamilyRelation::Self, 'template' => MeasurementTemplate::Gents]);

        return $customer;
    }

    public function show(Sale $sale): View|RedirectResponse
    {
        if ($sale->isHeld()) {
            return redirect()->route('sales.create', ['resume' => $sale->id]);
        }

        $sale->load('customer', 'creator', 'items.variant.product', 'items.variant.colour', 'payments', 'returns.creator', 'stitchingOrders');

        return view('sales.show', [
            'sale' => $sale,
            'shareUrl' => $this->shareUrl($sale),
        ]);
    }

    /**
     * Printable receipt in 80mm thermal or A4 format (SAL06).
     */
    public function receipt(Request $request, Sale $sale): View
    {
        return $this->receiptView($sale, $request->query('format') === 'a4' ? 'a4' : '80mm', true);
    }

    /**
     * Read-only receipt behind a signed link, for sharing on WhatsApp without logging in.
     */
    public function sharedReceipt(Sale $sale): View
    {
        abort_if($sale->isHeld(), 404);

        return $this->receiptView($sale, 'a4', false);
    }

    public function discard(Sale $sale): RedirectResponse
    {
        $this->sales->discardHeld($sale);

        return redirect()->route('sales.create')->with('success', __('Held bill :number discarded.', ['number' => $sale->number]));
    }

    private function receiptView(Sale $sale, string $format, bool $showToolbar): View
    {
        $sale->load('customer', 'creator', 'items.variant.product', 'items.variant.colour', 'payments');

        return view('sales.receipt', [
            'sale' => $sale,
            'format' => $format,
            'showToolbar' => $showToolbar,
            'shopName' => Setting::get('shop_name'),
            'currency' => Setting::get('currency'),
            'footer' => Setting::get('receipt_footer'),
        ]);
    }

    private function shareUrl(Sale $sale): string
    {
        return URL::temporarySignedRoute('receipts.shared', now()->addDays(30), ['sale' => $sale->id]);
    }

    /**
     * The discount on a bill line as a percentage, from a submitted percent or a saved rupee amount.
     *
     * @param  array<string, mixed>  $line
     */
    private function discountPercent(array $line): float|string
    {
        if (isset($line['discount_percent'])) {
            return $line['discount_percent'];
        }

        $lineAmount = (float) ($line['quantity'] ?? 0) * (float) ($line['price'] ?? 0);

        return $lineAmount > 0 && (float) ($line['discount'] ?? 0) > 0 ? round((float) $line['discount'] / $lineAmount * 100, 2) : '';
    }
}
