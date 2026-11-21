<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sale_id', 'product_variant_id', 'barcode_label_id', 'quantity', 'price', 'discount', 'amount', 'unit_cost', 'returned_qty'])]
class SaleItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'price' => 'decimal:2',
            'discount' => 'decimal:2',
            'amount' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'returned_qty' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * The price label this line was scanned from, if any.
     *
     * @return BelongsTo<BarcodeLabel, $this>
     */
    public function barcodeLabel(): BelongsTo
    {
        return $this->belongsTo(BarcodeLabel::class);
    }

    public function returnableQty(): float
    {
        return round((float) $this->quantity - (float) $this->returned_qty, 3);
    }
}
