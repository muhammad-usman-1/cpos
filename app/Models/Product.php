<?php

namespace App\Models;

use App\Enums\PieceType;
use App\Enums\ProductGender;
use App\Enums\ProductUnit;
use App\Enums\Season;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'design_code', 'name', 'category_id', 'brand_id', 'fabric_id', 'gender', 'season', 'piece_type', 'unit',
    'fabric_length', 'length_unit', 'cost_price', 'average_cost', 'sale_price', 'min_sale_price', 'description',
    'is_active', 'created_by',
])]
class Product extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'season' => Season::class,
            'gender' => ProductGender::class,
            'piece_type' => PieceType::class,
            'unit' => ProductUnit::class,
            'fabric_length' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'average_cost' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'min_sale_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Product $product): void {
            if ($product->code === null) {
                $product->forceFill(['code' => 'P'.str_pad((string) $product->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Fabric, $this>
     */
    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class)->withTrashed();
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * @return HasMany<ProductPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ProductPhoto::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Search by code, design code, name, brand, fabric, colour or a scanned variant barcode (STK11).
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';

        $query->where(function (Builder $query) use ($term, $like): void {
            $query->where('code', $term)
                ->orWhere('design_code', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhereHas('brand', fn (Builder $q) => $q->where('name', 'like', $like))
                ->orWhereHas('fabric', fn (Builder $q) => $q->where('name', 'like', $like))
                ->orWhereHas('variants', fn (Builder $q) => $q->where('sku', 'like', $term.'%')
                    ->orWhereHas('colour', fn (Builder $c) => $c->where('name', 'like', $like)));
        });
    }

    public function allowsFractionalQuantity(): bool
    {
        return $this->unit->isLooseFabric();
    }
}
