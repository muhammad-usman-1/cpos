<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseReturnRequest;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseReturnController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    public function create(Purchase $purchase): View|RedirectResponse
    {
        if ($purchase->isCancelled()) {
            return redirect()->route('purchases.show', $purchase)->with('error', __('A cancelled purchase cannot be returned.'));
        }

        $purchase->load('supplier', 'items.variant.product', 'items.variant.colour');

        return view('purchase-returns.create', ['purchase' => $purchase]);
    }

    public function store(PurchaseReturnRequest $request, Purchase $purchase): RedirectResponse
    {
        $return = $this->purchases->recordReturn($purchase, $request->validated());

        return redirect()->route('purchase-returns.show', $return)->with('success', __('Return :number saved. Stock reduced and supplier balance adjusted.', ['number' => $return->number]));
    }

    public function show(PurchaseReturn $purchaseReturn): View
    {
        $purchaseReturn->load('purchase', 'supplier', 'items.variant.product', 'items.variant.colour', 'creator');

        return view('purchase-returns.show', ['return' => $purchaseReturn]);
    }
}
