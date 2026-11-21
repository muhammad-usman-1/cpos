<?php

namespace App\Models;

use App\Enums\AdjustmentReason;
use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'product_variant_id', 'type', 'qty_in', 'qty_out', 'balance_after', 'unit_cost', 'reason', 'notes',
    'reference_type', 'reference_id', 'reversed_at', 'user_id', 'moved_at',
])]
class StockMovement extends Model
{
    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'reason' => AdjustmentReason::class,
            'qty_in' => 'decimal:3',
            'qty_out' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'reversed_at' => 'datetime',
            'moved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
