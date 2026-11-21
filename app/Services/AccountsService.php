<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StitchingOrder;
use App\Models\TailorAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AccountsService
{
    public const BUCKETS = ['0_30' => '0 to 30 days', '31_60' => '31 to 60 days', '60_plus' => '60+ days'];

    /**
     * Customers who owe money, with the balance split by age (PAY03).
     * Payments are assumed to clear the oldest charges first, so the balance is made of the newest charges.
     *
     * @return Collection<int, array{customer: Customer, balance: float, buckets: array<string, float>, oldest: Carbon|null}>
     */
    public function receivablesAgeing(?string $search = null): Collection
    {
        $customers = Customer::query()->where('balance', '>', 0)->search($search)->orderByDesc('balance')->get();

        $charges = LedgerEntry::query()
            ->where('party_type', (new Customer)->getMorphClass())
            ->whereIn('party_id', $customers->pluck('id'))
            ->where('debit', '>', 0)
            ->whereNull('reversed_at')
            ->where('type', '!=', LedgerEntry::REVERSAL)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get(['party_id', 'entry_date', 'debit'])
            ->groupBy('party_id');

        $today = today();

        return $customers->map(function (Customer $customer) use ($charges, $today): array {
            $remaining = (float) $customer->balance;
            $buckets = array_fill_keys(array_keys(self::BUCKETS), 0.0);
            $oldest = null;

            foreach ($charges->get($customer->id, collect()) as $charge) {
                if ($remaining <= 0.009) {
                    break;
                }

                $part = min($remaining, (float) $charge->debit);
                $days = $charge->entry_date->diffInDays($today);
                $buckets[$days <= 30 ? '0_30' : ($days <= 60 ? '31_60' : '60_plus')] += $part;
                $remaining -= $part;
                $oldest = $charge->entry_date;
            }

            if ($remaining > 0.009) {
                $buckets['60_plus'] += $remaining;
            }

            return [
                'customer' => $customer,
                'balance' => (float) $customer->balance,
                'buckets' => array_map(fn (float $amount) => round($amount, 2), $buckets),
                'oldest' => $oldest,
            ];
        });
    }

    /**
     * Gross profit on sales, stitching income and net profit after tailor payments for a period (PAY04).
     *
     * @return array<string, mixed>
     */
    public function profitSummary(string $from, string $to): array
    {
        $sales = Sale::query()->where('status', SaleStatus::Completed)->whereDate('sale_date', '>=', $from)->whereDate('sale_date', '<=', $to);

        $salesRevenue = (float) (clone $sales)->selectRaw('COALESCE(SUM(total - extra_charges), 0) as revenue')->value('revenue');
        $costOfSales = (float) (clone $sales)->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->selectRaw('COALESCE(SUM(sale_items.quantity * sale_items.unit_cost), 0) as cost')->value('cost');

        $returns = SaleReturn::query()->whereDate('return_date', '>=', $from)->whereDate('return_date', '<=', $to);
        $returnsValue = (float) (clone $returns)->sum('total');
        $returnedCost = (float) SaleReturnItem::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->whereNull('sale_returns.deleted_at')
            ->whereDate('sale_returns.return_date', '>=', $from)
            ->whereDate('sale_returns.return_date', '<=', $to)
            ->selectRaw('COALESCE(SUM(sale_return_items.quantity * sale_items.unit_cost), 0) as cost')
            ->value('cost');

        $netSales = round($salesRevenue - $returnsValue, 2);
        $netCost = round($costOfSales - $returnedCost, 2);
        $grossProfit = round($netSales - $netCost, 2);

        $stitchingIncome = (float) StitchingOrder::query()
            ->where('status', OrderStatus::Delivered)
            ->whereDate('delivered_at', '>=', $from)
            ->whereDate('delivered_at', '<=', $to)
            ->sum('total');

        $tailorCost = (float) TailorAssignment::query()
            ->whereNotNull('received_at')
            ->whereDate('received_at', '>=', $from)
            ->whereDate('received_at', '<=', $to)
            ->selectRaw('COALESCE(SUM(pieces_received * COALESCE(rate_per_piece, 0)), 0) as cost')
            ->value('cost');

        return [
            'sales' => round($salesRevenue, 2),
            'returns' => round($returnsValue, 2),
            'net_sales' => $netSales,
            'cost_of_sales' => $netCost,
            'gross_profit' => $grossProfit,
            'gross_margin' => $netSales > 0 ? round($grossProfit / $netSales * 100, 1) : 0,
            'stitching_income' => round($stitchingIncome, 2),
            'tailor_cost' => round((float) $tailorCost, 2),
            'net_profit' => round($grossProfit + $stitchingIncome - (float) $tailorCost, 2),
        ];
    }
}
