<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StitchingOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $stats = [];

        if ($user->can('products.view')) {
            $stats['products'] = Product::query()->where('is_active', true)->count();
            $stats['low_stock'] = ProductVariant::query()->lowStock()->count();
            $lowStock = ProductVariant::query()->lowStock()->with('product', 'colour')->orderBy('stock_qty')->limit(10)->get();
        }

        if ($user->can('cost.view')) {
            $stats['stock_value'] = (float) ProductVariant::query()
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereNull('product_variants.deleted_at')
                ->where('product_variants.stock_qty', '>', 0)
                ->selectRaw('SUM(product_variants.stock_qty * products.average_cost) as value')
                ->value('value');
        }

        if ($user->can('suppliers.view')) {
            $stats['payable'] = (float) Supplier::query()->sum('balance');
        }

        if ($user->can('purchases.view')) {
            $stats['purchases_today'] = (float) Purchase::query()
                ->where('status', PurchaseStatus::Posted)
                ->whereDate('purchase_date', today())
                ->sum('total');
        }

        if ($user->can('sales.view')) {
            $todaysSales = Sale::query()->where('status', SaleStatus::Completed)->whereDate('sale_date', today());
            $stats['sales_today'] = (float) (clone $todaysSales)->sum('total');
            $stats['bills_today'] = (clone $todaysSales)->count();
            $recentSales = Sale::query()->where('status', SaleStatus::Completed)->with('customer')->latest('id')->limit(8)->get();
        }

        if ($user->can('customers.view')) {
            $stats['receivable'] = (float) Customer::query()->where('balance', '>', 0)->sum('balance');
        }

        if ($user->can('orders.view-any')) {
            $orders = StitchingOrder::query()->visibleTo($user);
            $inProgress = array_filter(OrderStatus::workflow(), fn (OrderStatus $status) => $status !== OrderStatus::Ready);
            $stats['orders_due_today'] = (clone $orders)->whereIn('status', $inProgress)->whereDate('promised_date', today())->count();
            $stats['orders_overdue'] = (clone $orders)->whereIn('status', $inProgress)->whereDate('promised_date', '<', today())->count();
            $stats['orders_ready'] = (clone $orders)->where('status', OrderStatus::Ready)->count();
            $stats['orders_with_tailors'] = (clone $orders)->whereIn('status', $inProgress)->whereNotNull('tailor_id')->count();
            $ordersDue = (clone $orders)->whereIn('status', $inProgress)->with('customer', 'tailor')->orderBy('promised_date')->limit(8)->get();
        }

        return view('dashboard', [
            'stats' => $stats,
            'lowStock' => $lowStock ?? collect(),
            'recentSales' => $recentSales ?? collect(),
            'ordersDue' => $ordersDue ?? collect(),
        ]);
    }
}
