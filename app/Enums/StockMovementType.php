<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case PurchaseReturn = 'purchase_return';
    case Adjustment = 'adjustment';
    case StockTake = 'stock_take';
    case Reversal = 'reversal';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';

    public function label(): string
    {
        return match ($this) {
            self::Opening => __('Opening stock'),
            self::Purchase => __('Purchase'),
            self::PurchaseReturn => __('Purchase return'),
            self::Adjustment => __('Adjustment'),
            self::StockTake => __('Stock take'),
            self::Reversal => __('Reversal'),
            self::Sale => __('Sale'),
            self::SaleReturn => __('Sale return'),
        };
    }
}
