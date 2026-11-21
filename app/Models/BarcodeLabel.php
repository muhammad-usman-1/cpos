<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\BarcodeLabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A price label: its own scannable code, tied to one product, with a selling price that is used
 * instead of the product's normal price when the label is scanned at the counter.
 * The product's own price is never changed by a label.
 */
#[Fillable(['code', 'product_variant_id', 'price', 'copies_printed', 'is_active', 'notes', 'created_by'])]
class BarcodeLabel extends Model
{
    /** @use HasFactory<BarcodeLabelFactory> */
    use Auditable, HasFactory;

    /** Every price label code starts with this, so it can never be mistaken for a product's own barcode. */
    public const CODE_PREFIX = 'PL';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'copies_printed' => 'integer',
            'is_active' => 'boolean',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * PL0000001, PL0000002 … from the row id; a suffix is added in the rare case a product already uses that code.
     */
    public static function codeFor(int $id): string
    {
        $base = self::CODE_PREFIX.str_pad((string) $id, 7, '0', STR_PAD_LEFT);
        $code = $base;
        $suffix = 1;

        while (ProductVariant::withTrashed()->where('sku', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }
}
