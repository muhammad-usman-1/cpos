<?php

namespace App\Models;

use App\Enums\RefundMethod;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['number', 'sale_id', 'customer_id', 'return_date', 'total', 'refund_method', 'notes', 'created_by'])]
class SaleReturn extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'total' => 'decimal:2',
            'refund_method' => RefundMethod::class,
        ];
    }

    protected static function booted(): void
    {
        static::created(function (SaleReturn $return): void {
            $return->forceFill(['number' => 'SR-'.str_pad((string) $return->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return HasMany<SaleReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
