<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Http\Requests\PurchaseRequest;
use App\Models\Purchase;
use App\Models\Setting;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'supplier_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:posted,cancelled'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Purchase::query()
            ->with('supplier', 'creator')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('number', 'like', "%$term%")->orWhere('invoice_no', 'like', "%$term%")))
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('purchase_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('purchase_date', '<=', $to));

        $totals = (clone $query)->where('status', PurchaseStatus::Posted)
            ->selectRaw('COALESCE(SUM(total), 0) as total, COALESCE(SUM(paid_amount), 0) as paid')
            ->toBase()
            ->first();

        return view('purchases.index', [
            'purchases' => $query->latest('purchase_date')->latest('id')->paginate(25)->withQueryString(),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'shop_name']),
            'totals' => $totals,
        ]);
    }

    public function create(Request $request): View
    {
        return view('purchases.form', [
            'purchase' => new Purchase([
                'purchase_date' => today(),
                'supplier_id' => $request->query('supplier'),
                'payment_method' => PaymentMethod::Cash,
            ]),
            'items' => [],
        ] + $this->formOptions());
    }

    public function store(PurchaseRequest $request): RedirectResponse
    {
        $purchase = $this->purchases->create($request->purchaseData(), $request->file('attachment'));

        return redirect()->route('purchases.show', $purchase)->with('success', __('Purchase :number saved. Stock and supplier balance updated.', ['number' => $purchase->number]));
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load('supplier', 'items.variant.product', 'items.variant.colour', 'creator', 'canceller', 'returns.creator');

        return view('purchases.show', ['purchase' => $purchase]);
    }

    public function edit(Purchase $purchase): View|RedirectResponse
    {
        if (! $purchase->isEditable()) {
            return redirect()->route('purchases.show', $purchase)->with('error', __('Cancelled purchases and purchases with returns cannot be edited.'));
        }

        $purchase->load('items.variant.product', 'items.variant.colour');

        $items = $purchase->items->map(fn ($item): array => [
            'product_variant_id' => $item->product_variant_id,
            'label' => $item->variant->displayName(),
            'sku' => $item->variant->sku,
            'fractional' => $item->variant->product->allowsFractionalQuantity(),
            'quantity' => (float) $item->quantity,
            'rate' => (float) $item->rate,
        ])->all();

        return view('purchases.form', ['purchase' => $purchase, 'items' => $items] + $this->formOptions());
    }

    public function update(PurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->purchases->update($purchase, $request->purchaseData(), $request->file('attachment'));

        return redirect()->route('purchases.show', $purchase)->with('success', __('Purchase updated. The previous stock and ledger entries were reversed and re-posted.'));
    }

    public function cancel(Request $request, Purchase $purchase): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:200']]);

        $this->purchases->cancel($purchase, $validated['reason']);

        return redirect()->route('purchases.show', $purchase)->with('success', __('Purchase cancelled. Stock and supplier balance were reversed.'));
    }

    /**
     * Printable purchase voucher (PUR07).
     */
    public function voucher(Purchase $purchase): View
    {
        $purchase->load('supplier', 'items.variant.product', 'items.variant.colour', 'creator');

        return view('purchases.voucher', [
            'purchase' => $purchase,
            'shopName' => Setting::get('shop_name'),
            'currency' => Setting::get('currency'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'shop_name', 'balance']),
            'methods' => PaymentMethod::cases(),
        ];
    }
}
