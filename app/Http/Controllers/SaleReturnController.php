<?php

namespace App\Http\Controllers;

use App\Enums\RefundMethod;
use App\Http\Requests\SaleReturnRequest;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Returns and exchanges (SAL07). An exchange is a return followed by a new sale for the same customer.
 */
class SaleReturnController extends Controller
{
    public function __construct(private SaleService $sales) {}

    public function create(Sale $sale): View|RedirectResponse
    {
        if ($sale->isHeld()) {
            return redirect()->route('sales.create', ['resume' => $sale->id]);
        }

        $sale->load('customer', 'items.variant.product', 'items.variant.colour');

        return view('sale-returns.create', [
            'sale' => $sale,
            'factor' => $sale->netFactor(),
            'methods' => collect(RefundMethod::cases())->reject(fn (RefundMethod $method) => $method === RefundMethod::CustomerBalance && ! $sale->customer_id),
        ]);
    }

    public function store(SaleReturnRequest $request, Sale $sale): RedirectResponse
    {
        $return = $this->sales->recordReturn($sale, $request->validated());

        if ($request->boolean('exchange')) {
            return redirect()->route('sales.create', ['customer' => $sale->customer_id])
                ->with('success', __('Return :number saved (:amount). Now add the replacement items.', ['number' => $return->number, 'amount' => number_format($return->total, 2)]));
        }

        return redirect()->route('sale-returns.show', $return)->with('success', __('Return :number saved.', ['number' => $return->number]));
    }

    public function show(SaleReturn $saleReturn): View
    {
        $saleReturn->load('sale', 'customer', 'creator', 'items.variant.product', 'items.variant.colour');

        return view('sale-returns.show', ['return' => $saleReturn]);
    }
}
