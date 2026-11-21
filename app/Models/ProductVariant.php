<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['product_id', 'colour_id', 'sku', 'stock_qty', 'min_stock', 'is_active'])]
class ProductVariant extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock_qty' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (ProductVariant $variant): void {
            if ($variant->sku === null) {
                $variant->forceFill(['sku' => 'V'.str_pad((string) $variant->id, 7, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Colour, $this>
     */
    public function colour(): BelongsTo
    {
        return $this->belongsTo(Colour::class)->withTrashed();
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Variants at or below their minimum level (STK08).
     *
     * @param  Builder<self>  $query
     */
    public function scopeLowStock(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('min_stock', '>', 0)
            ->whereColumn('stock_qty', '<=', 'min_stock')
            ->whereHas('product', fn (Builder $q) => $q->where('is_active', true));
    }

    public function isLowStock(): bool
    {
        return (float) $this->min_stock > 0 && (float) $this->stock_qty <= (float) $this->min_stock;
    }

    /**
     * Human readable name such as "Wash and wear suit", or "Lawn print - Blue" for older products with colours.
     */
    public function displayName(): string
    {
        $name = $this->product->name;

        return $this->colour ? $name.' - '.$this->colour->name : $name;
    }
}
