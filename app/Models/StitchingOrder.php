<?php

namespace App\Models;

use App\Enums\ClothSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'number', 'type', 'parent_order_id', 'customer_id', 'customer_profile_id', 'measurement_id', 'cloth_source', 'sale_id',
    'cloth_description', 'cloth_quantity', 'pieces', 'design_notes', 'stitching_charges', 'extras_total', 'total',
    'paid_amount', 'status', 'order_date', 'promised_date', 'tailor_id', 'ready_at', 'delivered_at', 'cancelled_at',
    'cancel_reason', 'created_by',
])]
class StitchingOrder extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'cloth_source' => ClothSource::class,
            'status' => OrderStatus::class,
            'cloth_quantity' => 'decimal:2',
            'stitching_charges' => 'decimal:2',
            'extras_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'order_date' => 'date',
            'promised_date' => 'date',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (StitchingOrder $order): void {
            $order->forceFill(['number' => 'ORD-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
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
     * @return BelongsTo<CustomerProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Measurement, $this>
     */
    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)->withTrashed();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_order_id')->withTrashed();
    }

    /**
     * @return HasMany<self, $this>
     */
    public function alterations(): HasMany
    {
        return $this->hasMany(self::class, 'parent_order_id');
    }

    /**
     * @return HasMany<StitchingOrderExtra, $this>
     */
    public function extras(): HasMany
    {
        return $this->hasMany(StitchingOrderExtra::class);
    }

    /**
     * @return HasMany<OrderStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->orderBy('id');
    }

    /**
     * @return HasMany<TailorAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TailorAssignment::class)->orderBy('id');
    }

    /**
     * Every customer slip, suit tag and tailor slip printed for this order, newest first.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function slipPrints(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->where('event', 'slip_printed')->latest('id');
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'source');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function tailor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tailor_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', OrderStatus::workflow());
    }

    /**
     * Orders a tailor may see: the ones currently assigned to them.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('orders.view')) {
            $query->where('tailor_id', $user->id);
        }
    }

    public function balanceDue(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    public function isOverdue(): bool
    {
        return ! $this->status->isClosed() && $this->status !== OrderStatus::Ready && $this->promised_date->isBefore(today());
    }

    public function isEditable(): bool
    {
        return ! $this->status->isClosed();
    }
}
