<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_take_id', 'product_variant_id', 'system_qty', 'counted_qty', 'difference'])]
class StockTakeItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:3',
            'counted_qty' => 'decimal:3',
            'difference' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<StockTake, $this>
     */
    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}
