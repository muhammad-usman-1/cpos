<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(private StockService $stock) {}

    /**
     * Stock movement ledger for one variant (STK10).
     */
    public function ledger(Request $request, ProductVariant $variant): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'type' => ['nullable', 'string'],
        ]);

        $variant->load('product', 'colour');

        $movements = $variant->movements()
            ->with('user', 'reference')
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('moved_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('moved_at', '<=', $to))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('stock.ledger', [
            'variant' => $variant,
            'movements' => $movements,
            'types' => StockMovementType::cases(),
        ]);
    }

    /**
     * Low stock alert list (STK08).
     */
    public function lowStock(): View
    {
        $variants = ProductVariant::query()
            ->lowStock()
            ->with('product', 'colour')
            ->orderByRaw('stock_qty - min_stock')
            ->paginate(50);

        return view('stock.low', ['variants' => $variants]);
    }
}
