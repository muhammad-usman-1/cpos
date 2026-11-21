<?php

namespace App\Models;

use App\Enums\SaleStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'number', 'client_uuid', 'customer_id', 'status', 'sale_date', 'subtotal', 'item_discount', 'bill_discount', 'extra_charges',
    'total', 'paid_amount', 'cash_received', 'credit_amount', 'notes', 'created_by', 'completed_at',
])]
class Sale extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'sale_date' => 'date',
            'subtotal' => 'decimal:2',
            'item_discount' => 'decimal:2',
            'bill_discount' => 'decimal:2',
            'extra_charges' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Sale $sale): void {
            $sale->forceFill(['number' => 'INV-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<SaleReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'source');
    }

    /**
     * @return HasMany<StitchingOrder, $this>
     */
    public function stitchingOrders(): HasMany
    {
        return $this->hasMany(StitchingOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function isHeld(): bool
    {
        return $this->status === SaleStatus::Held;
    }

    /**
     * Share of each rupee of line amount that the customer actually paid after the bill discount.
     * Used to refund returns at the price really charged.
     */
    public function netFactor(): float
    {
        $lines = (float) $this->subtotal - (float) $this->item_discount;

        if ($lines <= 0) {
            return 0;
        }

        return max(0, ($lines - (float) $this->bill_discount) / $lines);
    }
}
